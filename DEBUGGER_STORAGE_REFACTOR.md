# Debugger Storage Refactor: Cache → File-Based Storage

## Problem

The debugger currently stores all its data using Laravel's `Cache` facade (`DebuggerMiddleware.php`). This means it depends on whatever cache driver the user has configured. If they're using `array` (common in testing), `null`, or a misconfigured driver, the debugger silently breaks. Cache can also be flushed at any time by the application, wiping debugger state mid-session.

### Current Cache Usage

Six calls in `DebuggerMiddleware.php`, three keys:

| Cache Key | Purpose | Access Pattern |
|---|---|---|
| `blaze_profiler_trace` | Full trace data for the profiler page | Written by middleware, read by `GET /_blaze/trace` |
| `blaze_page_times` | Render times per URL/mode (blade vs blaze, cold vs warm) | Read-modify-write in middleware |
| `blaze_seen_pages` | Tracks which URL+mode combos have been seen (cold detection) | Read-modify-write in middleware |

---

## Research: How Other Packages Handle This

### 1. Laravel Debugbar (php-debugbar/php-debugbar)

**Storage approach:** Each request stored as a separate JSON file in `storage/debugbar/`. File named by ULID (time-sortable unique ID).

**File writes — no locking:**
```php
// FileStorage.php
public function save(string $id, array $data): void
{
    file_put_contents($this->makeFilename($id), json_encode($data));
    $this->autoPrune();
}
```

Each request writes to its own file (filename = request ID), so two concurrent requests never touch the same file. No `LOCK_EX` needed.

**Cleanup — probabilistic auto-pruning:**
```php
// AbstractStorage.php
protected int|false $autoPrune = 24;       // hours
protected int $autoPruneProbability = 5;    // 5% chance per save

public function autoPrune(): void
{
    if (rand(1, 100) <= $this->autoPruneProbability) {
        $this->prune($this->autoPrune);
    }
}
```

The `prune()` method iterates `.json` files with `FilesystemIterator` and deletes any with `mtime` older than the cutoff. Simple, no index file needed.

**Directory bootstrapping:**
```php
if (!file_exists($this->dirname)) {
    mkdir($this->dirname, 0755, true);
    file_put_contents($this->dirname . '.gitignore', "*\n!.gitignore\n");
}
```

Auto-creates directory + `.gitignore` on first write.

**Key design choices:**
- Per-request files (no shared mutable state)
- ULID filenames (sortable by time)
- No locking anywhere
- No index file (scans directory for `find()`)
- 5% probabilistic pruning, 24-hour default TTL

---

### 2. Clockwork (itsgoingd/clockwork)

**Storage approach:** Each request stored as `{request-id}.json` in `storage/clockwork/`. Maintains a separate CSV index file for fast metadata searches.

**File writes — no locking on data files, locking on shared index:**
```php
// Data file: no lock (unique per request)
file_put_contents($path, $data . PHP_EOL);

// Shared index: LOCK_EX (multiple requests append concurrently)
$handle = fopen("{$this->path}/index", 'a');
flock($handle, LOCK_EX);
fputcsv($handle, [...]);
flock($handle, LOCK_UN);
fclose($handle);
```

**Cleanup — probabilistic (1% chance), time-based:**
```php
protected $cleanupChance = 100; // 1-in-100

public function cleanup($force = false)
{
    if ($this->expiration === false || (!$force && rand(1, $this->cleanupChance) != 1)) return;

    // Lock index, find expired entries, trim index, delete files
}
```

Default expiration: 7 days. Cleanup reads the index to find expired request IDs, trims the index file, then deletes the corresponding JSON files. Uses `LOCK_EX` on the index during cleanup to prevent concurrent corruption.

**Key design choices:**
- Per-request files (no shared mutable state for data)
- CSV index for fast search (avoids loading all JSON files)
- Locking only on the shared index file
- 1% probabilistic cleanup, 7-day default TTL
- Optional gzip compression
- `.gitignore` auto-created on first write

---

### 3. Laravel Telescope

**Storage approach:** Exclusively database-driven (3 tables: `telescope_entries`, `telescope_entries_tags`, `telescope_monitoring`). Requires a migration.

**Cleanup:** Artisan command `telescope:prune --hours=24`. Deletes in chunks to avoid memory issues. No automatic pruning — must be scheduled.

**Key design choices:**
- Database-only (overkill for our use case)
- Contract-based (`EntriesRepository` interface) for theoretical backend swapping
- In-memory queue flushed at request termination
- Chunk-based bulk inserts

Not relevant to our approach, but included for completeness.

---

## Comparison Summary

| | Debugbar | Clockwork | Telescope |
|---|---|---|---|
| **Storage** | JSON files | JSON files + CSV index | Database |
| **Naming** | `{ulid}.json` | `{timestamp-random}.json` | UUID in DB |
| **Locking** | None | Only on shared index | DB handles it |
| **Cleanup trigger** | 5% per save | 1% per store | Manual artisan |
| **Default TTL** | 24 hours | 7 days | Manual |
| **Setup required** | None (auto-creates dir) | None (auto-creates dir) | Migration |
| **Complexity** | Low | Medium | High |

---

## Our Approach

### Core Decision: One File Per Request

Store each request's trace data as an individual JSON file. This eliminates all locking concerns (each request writes to its own unique file) and gives us per-request history for free when we want it later.

For now, we only read the **latest** file. Adding history browsing later is just a new read path — the files are already there.

### What Changes

**Before:**
```
DebuggerMiddleware → Cache::get/put (6 calls, 3 keys)
Route /_blaze/trace → Cache::get
```

**After:**
```
DebuggerMiddleware → DebuggerStore → JSON files in storage/blaze/debugger/
Route /_blaze/trace → DebuggerStore::getLatestTrace()
```

### File Layout

```
storage/blaze/debugger/
├── .gitignore              # auto-created: *.json
├── page_state.json         # page times + seen pages (combined)
├── 01JQ8X...abc.json       # trace for request 1 (ULID filename)
├── 01JQ8X...def.json       # trace for request 2
└── 01JQ8X...ghi.json       # trace for request 3 (latest)
```

**Trace files** (`{ulid}.json`): One per request. Contains the profiler trace, debug bar data, URL, mode, timestamp. ULID filenames sort lexicographically by time, so "latest" = last file alphabetically.

**Page state file** (`page_state.json`): Contains `page_times` and `seen_pages`. This is the only shared mutable file. Use `LOCK_EX` on write as a safety net, though in practice concurrent writes to this file are unlikely to cause real problems in a dev tool. The data is small (a few KB at most) and the lock duration is microseconds.

### DebuggerStore Class

```php
class DebuggerStore
{
    protected string $path;

    public function __construct()
    {
        $this->path = storage_path('blaze/debugger');
    }

    // Per-request trace (one file per request, no locking needed)
    public function storeTrace(array $data): string     // returns request ID
    public function getLatestTrace(): ?array
    // Future: public function getTrace(string $id): ?array
    // Future: public function listTraces(int $max = 20): array

    // Shared page state (single file, LOCK_EX on write)
    public function getPageState(): array               // ['times' => [...], 'seen' => [...]]
    public function putPageState(array $state): void

    // Cleanup
    public function prune(int $hours = 24): void        // called probabilistically
    public function clear(): void                       // for artisan command
}
```

### Cleanup Strategy

Follow Debugbar's approach — simple and proven:

- **Probabilistic auto-prune**: 5% chance on each `storeTrace()` call
- **Default TTL**: 24 hours (delete trace files with `mtime` older than cutoff)
- **Method**: `FilesystemIterator` + `mtime` check + `unlink` (same as Debugbar)
- **Page state**: never auto-pruned (it's one small file). Cleared by `blaze:clear` or `view:clear`.

No index file needed. We don't need Clockwork's CSV index because we're not doing search/filter operations — just "get latest."

### Concurrent Writes: Not a Problem

Both Debugbar and Clockwork prove this approach is battle-tested:

- **Trace files**: Each request writes its own unique file. No contention possible.
- **Page state file**: Uses `file_put_contents($path, $json, LOCK_EX)`. The `LOCK_EX` flag is a single syscall adding ~0.01ms. Won't affect profiler accuracy.

### Performance Impact on Profiling

Concern: file I/O in a profiler could skew measurements.

Reality: The storage happens in the middleware **after** `$response = $next($request)` — after the render timer has already stopped. The profiler measures rendering time inside `startRenderTimer()`/`stopRenderTimer()`, and the trace entries record `hrtime` during rendering. By the time we write to disk, all measurements are already captured. File I/O happens in the "bookkeeping" phase, not the "measurement" phase.

---

## Implementation Plan

### Step 1: Extract DebuggerStore + simplify debug bar + add compilation tracking

Create `DebuggerStore` class with two methods: `storeTrace()` and `getLatestTrace()`. Initialized inside `Debugger` and exposed via `$debugger->store`. Uses `Cache` internally for now.

Remove comparison/page state entirely:
- Delete `recordAndCompare()` from `DebuggerMiddleware` (and the `Cache` calls it contained)
- Delete `$comparison`, `$isColdRender`, `setComparison()`, `setIsColdRender()`, `renderSavingsBlock()`, `renderSavingsRow()` from `Debugger`
- Debug bar shows only the current render time

Add compilation tracking:
- `Debugger::recordCompilation(string $path)` — incremented via the existing `prepareStringsForCompilationUsing` hook in `BlazeServiceProvider::interceptBladeCompilation()`
- Compilation count + list stored in trace data alongside everything else

### Step 2: Swap to file storage

Replace the internals of `DebuggerStore`:
- `storeTrace()` → write `{ulid}.json` to `storage/blaze/debugger/`
- `getLatestTrace()` → find most recent `.json` file
- Add `ensureDirectoryExists()` with `.gitignore` creation
- Add probabilistic `autoPrune()` (5% chance, 24h TTL)

Remove `use Illuminate\Support\Facades\Cache` from the store.

### Step 3 (future): Add back comparisons using compilation count

Re-introduce the comparison widget in the debug bar, but smarter:
- Use compilation count per request instead of the binary cold/warm heuristic
- Show compilation count alongside render time so the user can judge whether a comparison is fair
- Store cross-request comparison data (approach TBD — could be a small `page_times.json` or derived from recent trace files)

### Step 4 (future): Per-request history

When we want history browsing in the profiler UI:
- Add `getTrace(string $id): ?array` to `DebuggerStore`
- Add `listTraces(int $max): array` — scan directory, sort by filename (ULIDs sort chronologically)
- Add `GET /_blaze/traces` route returning list of recent traces
- Update profiler UI with a request picker dropdown

The files are already there from Step 2. This is purely a new read path + UI work.

---

## Things to Watch Out For

- **Octane**: `Debugger::flushState()` resets in-memory state between requests. File storage doesn't need flushing, but `DebuggerStore` should be stateless (no in-memory cache of file contents between requests).
- **Don't over-abstract**: No `StorageInterface` needed. A single concrete `DebuggerStore` class is fine. Extract an interface later if we ever need swappable backends.
- **Directory permissions**: Use `0755` for the directory (matching Debugbar). The `.gitignore` prevents committing debug data.
- **`storage/blaze/` path**: Consider that other Blaze features might want storage space too (compiled view cache, etc.). Using `storage/blaze/debugger/` as a subdirectory leaves room for siblings.
