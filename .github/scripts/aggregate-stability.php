<?php

/**
 * Aggregates benchmark stability results across multiple runs.
 *
 * Reads JSON stats files produced by `benchmark --dump` and generates
 * a markdown report ranking parameter combinations by cross-run stability.
 *
 * Usage: php aggregate-stability.php <stats-directory>
 */

$dir = $argv[1] ?? 'stats';

// Each config directory contains stats-rep1.json .. stats-rep5.json.
$files = glob("$dir/*/stats-rep*.json");

if (empty($files)) {
    fwrite(STDERR, "No stats files found in $dir\n");
    exit(1);
}

// Group runs by config (iterations x rounds x warmup).
$byConfig = [];

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);

    if (! $data || ! isset($data['config'], $data['benchmarks'])) {
        fwrite(STDERR, "Skipping invalid file: $file\n");
        continue;
    }

    $key = sprintf(
        'i%05d-r%02d-w%d',
        $data['config']['iterations'],
        $data['config']['rounds'],
        $data['config']['warmup'],
    );

    $byConfig[$key]['config'] = $data['config'];
    $byConfig[$key]['runs'][] = $data['benchmarks'];
}

ksort($byConfig);

function computeStats(array $values): array
{
    $n = count($values);

    if ($n === 0) {
        return ['mean' => 0, 'stddev' => 0, 'cv' => 0, 'min' => 0, 'max' => 0];
    }

    $mean = array_sum($values) / $n;
    $variance = array_reduce(
        $values,
        fn ($carry, $v) => $carry + ($v - $mean) ** 2,
        0
    ) / max($n - 1, 1);

    $stddev = sqrt($variance);
    $cv = $mean > 0 ? ($stddev / $mean) * 100 : 0;

    return [
        'mean' => $mean,
        'stddev' => $stddev,
        'cv' => $cv,
        'min' => min($values),
        'max' => max($values),
    ];
}

// --- Report ---

echo "# Benchmark Stability Report\n\n";

// Overall ranking table.
echo "## Ranking (sorted by Blaze cross-run CV%)\n\n";
echo "Lower CV% = more stable across repeated runs on the same machine.\n\n";
echo "| Config | Runs | Blaze cross-run CV% | Blade cross-run CV% | Avg Blaze within-run CV% | Verdict |\n";
echo "|--------|------|--------------------:|--------------------:|-------------------------:|--------:|\n";

$rankings = [];

foreach ($byConfig as $key => $group) {
    $config = $group['config'];
    $runs = $group['runs'];
    $benchNames = array_keys($runs[0]);

    $blazeCrossRunCvs = [];
    $bladeCrossRunCvs = [];
    $blazeWithinRunCvs = [];

    foreach ($benchNames as $bench) {
        $blazeMedians = array_map(fn ($r) => $r[$bench]['blaze']['median'], $runs);
        $bladeMedians = array_map(fn ($r) => $r[$bench]['blade']['median'], $runs);

        $blazeCrossRunCvs[] = computeStats($blazeMedians)['cv'];
        $bladeCrossRunCvs[] = computeStats($bladeMedians)['cv'];

        foreach ($runs as $run) {
            $blazeWithinRunCvs[] = $run[$bench]['blaze']['cv_percent'];
        }
    }

    $avgBlazeCrossRunCv = array_sum($blazeCrossRunCvs) / count($blazeCrossRunCvs);
    $avgBladeCrossRunCv = array_sum($bladeCrossRunCvs) / count($bladeCrossRunCvs);
    $avgBlazeWithinRunCv = array_sum($blazeWithinRunCvs) / count($blazeWithinRunCvs);

    if ($avgBlazeCrossRunCv < 3.0) {
        $verdict = 'EXCELLENT';
    } elseif ($avgBlazeCrossRunCv < 5.0) {
        $verdict = 'GOOD';
    } elseif ($avgBlazeCrossRunCv < 10.0) {
        $verdict = 'FAIR';
    } else {
        $verdict = 'POOR';
    }

    $label = sprintf(
        '%dk iter x %d rounds x %d warmup',
        $config['iterations'] / 1000,
        $config['rounds'],
        $config['warmup'],
    );

    $rankings[] = [
        'key' => $key,
        'label' => $label,
        'runs' => count($runs),
        'blaze_cross_cv' => $avgBlazeCrossRunCv,
        'blade_cross_cv' => $avgBladeCrossRunCv,
        'blaze_within_cv' => $avgBlazeWithinRunCv,
        'verdict' => $verdict,
    ];
}

// Sort by blaze cross-run CV ascending (most stable first).
usort($rankings, fn ($a, $b) => $a['blaze_cross_cv'] <=> $b['blaze_cross_cv']);

foreach ($rankings as $r) {
    printf(
        "| %-30s | %d | %5.1f%% | %5.1f%% | %5.1f%% | %-9s |\n",
        $r['label'],
        $r['runs'],
        $r['blaze_cross_cv'],
        $r['blade_cross_cv'],
        $r['blaze_within_cv'],
        $r['verdict'],
    );
}

echo "\n---\n\n";

// Detailed per-config tables.
foreach ($byConfig as $key => $group) {
    $config = $group['config'];
    $runs = $group['runs'];
    $numRuns = count($runs);

    $label = sprintf(
        '%dk iterations x %d rounds x %d warmup',
        $config['iterations'] / 1000,
        $config['rounds'],
        $config['warmup'],
    );

    echo "## $label ($numRuns runs)\n\n";
    echo "| Benchmark | Engine | Medians (per run) | Cross-run CV% | Avg within-run CV% | Stable? |\n";
    echo "|-----------|--------|-------------------|:-------------:|:-------------------:|:-------:|\n";

    $benchNames = array_keys($runs[0]);

    foreach ($benchNames as $bench) {
        foreach (['blade', 'blaze'] as $engine) {
            $medians = array_map(fn ($r) => $r[$bench][$engine]['median'], $runs);
            $withinCvs = array_map(fn ($r) => $r[$bench][$engine]['cv_percent'], $runs);

            $stats = computeStats($medians);
            $avgWithinCv = array_sum($withinCvs) / count($withinCvs);

            $medianStr = implode(', ', array_map(fn ($v) => sprintf('%.1f', $v), $medians));

            if ($stats['cv'] < 3.0) {
                $stable = 'YES';
            } elseif ($stats['cv'] < 5.0) {
                $stable = 'OK';
            } elseif ($stats['cv'] < 10.0) {
                $stable = 'FAIR';
            } else {
                $stable = 'NO';
            }

            printf(
                "| %-25s | %-5s | %s | %.1f%% | %.1f%% | %s |\n",
                $bench,
                strtoupper($engine),
                $medianStr,
                $stats['cv'],
                $avgWithinCv,
                $stable,
            );
        }
    }

    echo "\n";
}
