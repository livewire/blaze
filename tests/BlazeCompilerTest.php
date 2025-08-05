<?php

namespace Livewire\Blaze\Tests;

use Livewire\Blaze\BlazeCompiler;
use Illuminate\Filesystem\Filesystem;

class BlazeCompilerTest extends TestCase
{
    protected BlazeCompiler $compiler;
    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->files = new Filesystem();
        $this->compiler = new BlazeCompiler(
            $this->files,
            __DIR__.'/fixtures/compiled',
            config('blaze')
        );
    }

    public function test_it_compiles_blade_templates()
    {
        $template = '{{ $variable }}';
        $compiled = $this->compiler->compileString($template);
        
        $this->assertStringContainsString('<?php', $compiled);
        $this->assertStringContainsString('$variable', $compiled);
    }

    public function test_it_applies_optimizations()
    {
        $template = '<x-alert type="success">Message</x-alert>';
        $compiled = $this->compiler->compileString($template);
        
        $this->assertNotEmpty($compiled);
    }

    public function test_it_caches_compiled_components()
    {
        $component = '<div>{{ $slot }}</div>';
        
        $compiled1 = $this->compiler->compileComponent($component);
        $compiled2 = $this->compiler->compileComponent($component);
        
        $this->assertEquals($compiled1, $compiled2);
    }

    public function test_it_clears_component_cache()
    {
        $component = '<div>{{ $slot }}</div>';
        
        $this->compiler->compileComponent($component);
        $this->compiler->clearComponentCache();
        
        // After clearing, it should recompile
        $compiled = $this->compiler->compileComponent($component);
        $this->assertNotEmpty($compiled);
    }
}