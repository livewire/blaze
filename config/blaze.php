<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Blaze
    |--------------------------------------------------------------------------
    |
    | When set to false, Blaze will skip all compilation and optimization.
    | Templates will be rendered using standard Blade without any Blaze
    | processing. This is useful for debugging or disabling Blaze in
    | specific environments.
    |
    */

    'enabled' => env('BLAZE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, Blaze will register the debugger middleware and output
    | additional diagnostic information about the compilation pipeline.
    |
    */

    'debug' => env('BLAZE_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Blaze Compiled Path
    |--------------------------------------------------------------------------
    |
    | The directory where Blaze writes compiled component PHP files.
    | Using a dedicated path avoids development watcher loops triggered
    | by frequent writes in the default Blade compiled views directory.
    |
    */

    'compiled_path' => env('BLAZE_COMPILED_PATH', storage_path('framework/blaze')),

];
