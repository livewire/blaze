<?php

use Livewire\Blaze\Folder\SlotUsageAnalyzer;

describe('SlotUsageAnalyzer', function () {
    
    describe('with no slots', function () {
        it('allows folding when no slots are passed', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>Static content only</div>';
            
            expect($analyzer->canFoldWithSlots($source, []))->toBeTrue();
        });
    });
    
    describe('with simple slot echoing', function () {
        it('allows folding when slot is only echoed with {{ }}', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('allows folding when slot is echoed unescaped with {!! !!}', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{!! $slot !!}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('allows folding with multiple simple slot echoes', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot }} <span>{{ $slot }}</span></div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('allows folding with mixed escaped and unescaped echoes', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot }} {!! $slot !!}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('allows folding with multiple named slots', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<header>{{ $header }}</header><main>{{ $slot }}</main><footer>{{ $footer }}</footer>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot', 'header', 'footer']))->toBeTrue();
        });
    });
    
    describe('with slots in PHP blocks', function () {
        it('aborts when slot is used in @php block', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@php $content = $slot; @endphp <div>{{ $content }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in standard PHP block', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<?php $content = $slot; ?> <div>{{ $content }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('allows when different slot is in PHP block', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@php $x = $other; @endphp <div>{{ $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
    });
    
    describe('with slots in Blade directives', function () {
        it('aborts when slot is used in @if directive', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@if($slot) <div>{{ $slot }}</div> @endif';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in @isset directive', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@isset($slot) <div>{{ $slot }}</div> @endisset';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in @unless directive', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@unless($slot->isEmpty()) <div>{{ $slot }}</div> @endunless';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in @foreach directive', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@foreach($slot as $item) {{ $item }} @endforeach';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('allows when slot is not in directive expression', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@if($condition) {{ $slot }} @endif';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
    });
    
    describe('with slot transformations', function () {
        it('aborts when slot is concatenated', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ "Prefix: " . $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot has method call', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot->toHtml() }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used with null coalesce', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot ?? "default" }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in ternary', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slot ? "has content" : "empty" }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used as function argument', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ strtoupper($slot) }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is used in array', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ ["content" => $slot] }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
        
        it('aborts when slot is passed to child component', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<x-child :content="$slot" />';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeFalse();
        });
    });
    
    describe('with @unblaze blocks', function () {
        it('ignores slot usage inside @unblaze blocks', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@unblaze @if($slot) dynamic @endif @endunblaze <div>{{ $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('ignores complex slot usage inside @unblaze blocks', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '@unblaze {{ $slot->toHtml() }} @endunblaze <div>{{ $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
    });
    
    describe('edge cases', function () {
        it('handles slots with whitespace in echo', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{   $slot   }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('distinguishes between different variable names', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>{{ $slotName }} {{ $slot }}</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('handles empty source', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
        
        it('handles source with no slot usage', function () {
            $analyzer = new SlotUsageAnalyzer();
            
            $source = '<div>Static content</div>';
            
            expect($analyzer->canFoldWithSlots($source, ['slot']))->toBeTrue();
        });
    });
});