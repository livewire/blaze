<?php
use Livewire\Blaze\Blaze;
use Livewire\Blaze\Support\Utils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures');
});

describe('comment handling', function () {
    it('ignores blade comments inside component attributes', function () {
        $result = \Illuminate\Support\Facades\Blade::render('<x-simple-button type="submit" {{-- ignore me --}} />');

        expect($result)->toContain('button');
        expect($result)->not->toContain('ignore me');
    });

    it('ignores component tags inside php comments', function () {
        $result = \Illuminate\Support\Facades\Blade::render(<<<EOT
<div>Before</div>
@php
// <x-simple-button />
echo 'Middle';
@endphp
<div>After</div>
EOT);

        expect($result)->toContain('Before');
        expect($result)->toContain('Middle');
        expect($result)->toContain('After');
        expect($result)->not->toContain('<button');
    });
});

describe('nested component folding', function () {
    it('folds child with fold:true inside parent with bare @blaze', function () {
        $wrapperSource = file_get_contents(__DIR__ . '/fixtures/nested/wrapper.blade.php');
        $compiled = app('blaze')->compile($wrapperSource);

        expect($compiled)->toContain('<span class="child">I am folded</span>');
        expect($compiled)->not->toContain('<x-nested.foldable-child');
    });

    it('does not fold child with bare @blaze inside parent with bare @blaze', function () {
        $wrapperSource = file_get_contents(__DIR__ . '/fixtures/nested/wrapper-with-unfoldable.blade.php');
        $compiled = app('blaze')->compile($wrapperSource);

        // With function compilation as default, the child tag should be transformed to a function call
        expect($compiled)->not->toContain('<x-nested.unfoldable-child');
        expect($compiled)->toMatch('/<\?php.*_[a-f0-9]+\(/'); // Should contain a function call
    });

    it('renders wrapper with folded child through Blade', function () {
        $result = \Illuminate\Support\Facades\Blade::render('<x-nested.wrapper />');

        expect($result)->toContain('<div class="wrapper">');
        expect($result)->toContain('<span class="child">I am folded</span>');
    });

    it('renders wrapper with unfoldable child through Blade', function () {
        $result = \Illuminate\Support\Facades\Blade::render('<x-nested.wrapper-with-unfoldable />');

        expect($result)->toContain('<div class="wrapper">');
        expect($result)->toContain('<span class="child">I am not folded</span>');
    });

    it('renders wrapper with non-blaze child through Blade', function () {
        $result = \Illuminate\Support\Facades\Blade::render('<x-nested.wrapper-with-non-blaze />');

        expect($result)->toContain('<div class="wrapper">');
        expect($result)->toContain('<button type="submit">Click me</button>');
    });

    it('compiles wrapper with function wrapper and folded child', function () {
        $wrapperPath = __DIR__ . '/fixtures/nested/wrapper.blade.php';
        $hash = Utils::hash($wrapperPath);
        $compiledPath = app('blade.compiler')->getCompiledPath($wrapperPath);

        if (File::exists($compiledPath)) {
            File::delete($compiledPath);
        }

        app('blade.compiler')->compile($wrapperPath);
        $compiled = File::get($compiledPath);

        expect($compiled)->toContain("function _$hash(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [], \$__this = null)");
        expect($compiled)->toContain('<span class="child">I am folded</span>');
        expect($compiled)->not->toContain('<x-nested.foldable-child');
        expect($compiled)->not->toContain('$__blaze->ensureCompiled');
    });
});

describe('shared view variables', function () {
    it('has access to $errors when shared via View::share', function () {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['email' => 'The email field is required.']));
        app('view')->share('errors', $errors);

        $result = blade(
            components: [
                'error-display' => <<<'BLADE'
                    @blaze
                    <div>
                        @if($errors->any())
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        @else
                            <p>No errors</p>
                        @endif
                    </div>
                    BLADE
                ,
            ],
            view: '<x-error-display />',
        );

        expect($result)->toContain('The email field is required.');
    });

    it('shows no errors when empty ViewErrorBag is shared', function () {
        app('view')->share('errors', new ViewErrorBag);

        $result = blade(
            components: [
                'error-display' => <<<'BLADE'
                    @blaze
                    <div>
                        @if($errors->any())
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        @else
                            <p>No errors</p>
                        @endif
                    </div>
                    BLADE
                ,
            ],
            view: '<x-error-display />',
        );

        expect($result)->toContain('No errors');
    });
});

describe('path-based optimization via Blaze::optimize()', function () {
    beforeEach(function () {
        Blaze::optimize()->clear();
        app('blade.compiler')->anonymousComponentPath(__DIR__ . '/fixtures/path-config');
    });

    afterEach(function () {
        Blaze::optimize()->clear();
    });

    it('renders a component enabled via Blaze::optimize() without @blaze directive', function () {
        Blaze::optimize()->in(__DIR__ . '/fixtures/path-config');

        $result = \Illuminate\Support\Facades\Blade::render('<x-button type="submit">Click me</x-button>');

        expect($result)->toContain('<button');
        expect($result)->toContain('Click me');
        expect($result)->toContain('type="submit"');
    });

    it('wraps component source in function when enabled via Blaze::optimize()', function () {
        Blaze::optimize()->in(__DIR__ . '/fixtures/path-config');

        $path = __DIR__ . '/fixtures/path-config/button.blade.php';
        $hash = Utils::hash($path);

        $compiledPath = app('blade.compiler')->getCompiledPath($path);

        if (File::exists($compiledPath)) {
            File::delete($compiledPath);
        }

        app('blade.compiler')->compile($path);
        $compiled = File::get($compiledPath);

        expect($compiled)->toContain("function _$hash(\$__blaze, \$__data = [], \$__slots = [], \$__bound = [], \$__this = null)");
    });
});
