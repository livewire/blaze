<?php

namespace Livewire\Blaze\Tests;

use Livewire\Blaze\BlazeCache;
use Illuminate\Support\Facades\Cache;

class BlazeCacheTest extends TestCase
{
    protected BlazeCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cache = new BlazeCache();
    }

    public function test_it_remembers_values()
    {
        $value = $this->cache->remember('test_key', function () {
            return 'test_value';
        });
        
        $this->assertEquals('test_value', $value);
        
        // Second call should return cached value
        $cachedValue = $this->cache->remember('test_key', function () {
            return 'different_value';
        });
        
        $this->assertEquals('test_value', $cachedValue);
    }

    public function test_it_tracks_rendered_components()
    {
        $this->assertFalse($this->cache->hasRendered('component1'));
        $this->assertTrue($this->cache->hasRendered('component1'));
        
        // Second call should return true
        $this->assertTrue($this->cache->hasRendered('component1'));
    }

    public function test_it_flushes_cache()
    {
        $this->cache->remember('key1', fn() => 'value1');
        $this->cache->hasRendered('component1');
        
        $this->cache->flush();
        
        // After flush, hasRendered should return false again
        $this->assertFalse($this->cache->hasRendered('component1'));
    }

    public function test_it_forgets_specific_keys()
    {
        $this->cache->remember('key1', fn() => 'value1');
        $this->cache->remember('key2', fn() => 'value2');
        
        $this->cache->forget('key1');
        
        // key1 should be forgotten, but key2 should remain
        $value1 = $this->cache->remember('key1', fn() => 'new_value1');
        $value2 = $this->cache->remember('key2', fn() => 'new_value2');
        
        $this->assertEquals('new_value1', $value1);
        $this->assertEquals('value2', $value2);
    }

    public function test_should_render_flag()
    {
        $this->assertTrue(BlazeCache::shouldRender());
        
        BlazeCache::setShouldRender(false);
        $this->assertFalse(BlazeCache::shouldRender());
        
        BlazeCache::setShouldRender(true);
        $this->assertTrue(BlazeCache::shouldRender());
    }
}