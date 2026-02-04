<?php

use Livewire\Blaze\OptimizeBuilder;

describe('OptimizeBuilder', function () {
    it('can register a directory', function () {
        $builder = new OptimizeBuilder;

        $result = $builder->in('/path/to/components');

        expect($result)->toBe($builder);
        expect($builder->hasPaths())->toBeTrue();
    });

    it('can register multiple directories', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components')
            ->in('/path/to/icons');

        expect($builder->getPaths())->toHaveCount(2);
    });

    it('normalizes trailing slashes', function () {
        $builder = new OptimizeBuilder;

        $builder->in('/path/to/components/');

        expect($builder->getPaths())->toHaveKey('/path/to/components');
    });

    it('stores compile, fold, and memo options', function () {
        $builder = new OptimizeBuilder;

        $builder->in('/path/to/components', compile: true, fold: true, memo: false);

        $config = $builder->getConfigForPath('/path/to/components/button.blade.php');

        expect($config)->toBe([
            'compile' => true,
            'fold' => true,
            'memo' => false,
        ]);
    });

    it('defaults compile to true, fold to false, memo to false', function () {
        $builder = new OptimizeBuilder;

        $builder->in('/path/to/components');

        $config = $builder->getConfigForPath('/path/to/components/button.blade.php');

        expect($config)->toBe([
            'compile' => true,
            'fold' => false,
            'memo' => false,
        ]);
    });

    it('returns null when no path matches', function () {
        $builder = new OptimizeBuilder;

        $builder->in('/path/to/components');

        $config = $builder->getConfigForPath('/other/path/button.blade.php');

        expect($config)->toBeNull();
    });

    it('matches the most specific path', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components', fold: false)
            ->in('/path/to/components/ui', fold: true);

        // Should match /path/to/components/ui (more specific)
        $config = $builder->getConfigForPath('/path/to/components/ui/button.blade.php');
        expect($config['fold'])->toBeTrue();

        // Should match /path/to/components (less specific)
        $config = $builder->getConfigForPath('/path/to/components/alert.blade.php');
        expect($config['fold'])->toBeFalse();
    });

    it('can exclude directories with compile: false', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components')
            ->in('/path/to/components/legacy', compile: false);

        expect($builder->shouldCompile('/path/to/components/button.blade.php'))->toBeTrue();
        expect($builder->shouldCompile('/path/to/components/legacy/old.blade.php'))->toBeFalse();
    });

    it('shouldFold returns path-based fold setting', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components', fold: false)
            ->in('/path/to/components/cards', fold: true);

        expect($builder->shouldFold('/path/to/components/button.blade.php'))->toBeFalse();
        expect($builder->shouldFold('/path/to/components/cards/card.blade.php'))->toBeTrue();
        expect($builder->shouldFold('/other/path/file.blade.php'))->toBeNull();
    });

    it('shouldMemo returns path-based memo setting', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components', memo: false)
            ->in('/path/to/components/icons', memo: true);

        expect($builder->shouldMemo('/path/to/components/button.blade.php'))->toBeFalse();
        expect($builder->shouldMemo('/path/to/components/icons/icon.blade.php'))->toBeTrue();
        expect($builder->shouldMemo('/other/path/file.blade.php'))->toBeNull();
    });

    it('hasPaths returns false when no paths configured', function () {
        $builder = new OptimizeBuilder;

        expect($builder->hasPaths())->toBeFalse();
    });

    it('allows overwriting existing path config', function () {
        $builder = new OptimizeBuilder;

        $builder
            ->in('/path/to/components', fold: false)
            ->in('/path/to/components', fold: true);

        expect($builder->shouldFold('/path/to/components/button.blade.php'))->toBeTrue();
    });
})->skip();
