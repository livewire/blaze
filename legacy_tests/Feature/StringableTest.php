<?php

use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    app('blade.compiler')->anonymousComponentPath(__DIR__.'/fixtures');
});

describe('Blade::stringable()', function () {
    it('renders objects with registered stringable handlers', function () {
        // Register a stringable handler for a simple class
        Blade::stringable(function (TestMoney $money) {
            return '$'.number_format($money->amount / 100, 2);
        });

        $result = blade(
            components: [
                'price' => <<<'BLADE'
                    @blaze
                    @props(['price'])
                    <span class="price">{{ $price }}</span>
                    BLADE
                ,
            ],
            view: '<x-price :price="$money" />',
            data: ['money' => new TestMoney(1999)],
        );

        expect($result)->toContain('$19.99');
        expect($result)->toContain('class="price"');
    });

    it('passes through non-matching objects unchanged', function () {
        // Ensure no handler for PlainObject
        $result = blade(
            components: [
                'display' => <<<'BLADE'
                    @blaze
                    @props(['value'])
                    <span>{{ $value }}</span>
                    BLADE
                ,
            ],
            view: '<x-display :value="$obj" />',
            data: ['obj' => new TestStringableObject('Hello World')],
        );

        expect($result)->toContain('Hello World');
    });

    it('works with multiple echo statements', function () {
        Blade::stringable(function (TestMoney $money) {
            return '$'.number_format($money->amount / 100, 2);
        });

        $result = blade(
            components: [
                'totals' => <<<'BLADE'
                    @blaze
                    @props(['subtotal', 'tax', 'total'])
                    <div>
                        <span>Subtotal: {{ $subtotal }}</span>
                        <span>Tax: {{ $tax }}</span>
                        <span>Total: {{ $total }}</span>
                    </div>
                    BLADE
                ,
            ],
            view: '<x-totals :subtotal="$sub" :tax="$tax" :total="$tot" />',
            data: [
                'sub' => new TestMoney(1000),
                'tax' => new TestMoney(100),
                'tot' => new TestMoney(1100),
            ],
        );

        expect($result)->toContain('Subtotal: $10.00');
        expect($result)->toContain('Tax: $1.00');
        expect($result)->toContain('Total: $11.00');
    });

    it('works without @props directive', function () {
        Blade::stringable(function (TestMoney $money) {
            return '$'.number_format($money->amount / 100, 2);
        });

        $result = blade(
            components: [
                'simple' => <<<'BLADE'
                    @blaze
                    <span>{{ $amount }}</span>
                    BLADE
                ,
            ],
            view: '<x-simple :amount="$money" />',
            data: ['money' => new TestMoney(2500)],
        );

        expect($result)->toContain('$25.00');
    });

    it('compiles with $__bladeCompiler when echo handlers are registered', function () {
        Blade::stringable(function (TestMoney $money) {
            return '$'.number_format($money->amount / 100, 2);
        });

        // Manually compile a component with an echo that uses the handler
        $componentSource = <<<'BLADE'
            @blaze
            @props(['price'])
            <span>{{ $price }}</span>
            BLADE;

        // Compile through Blade compiler
        $compiled = app('blade.compiler')->compileString($componentSource);

        // Verify $__bladeCompiler is injected inside the function
        expect($compiled)->toContain('$__bladeCompiler = app(\'blade.compiler\')');
        expect($compiled)->toContain('$__bladeCompiler->applyEchoHandler');
    });
});

// Simple test class to use with stringable
class TestMoney
{
    public function __construct(public int $amount) {}
}

class TestStringableObject
{
    public function __construct(public string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
