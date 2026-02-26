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
    | Blaze Folded View Cache Path
    |--------------------------------------------------------------------------
    |
    | The directory where Blaze writes temporary folded view caches during
    | isolated rendering. Keeping these files outside the default Blade
    | compiled views path helps avoid watcher-driven reload loops in dev.
    |
    */

    'folded_view_cache_path' => env('BLAZE_FOLDED_VIEW_CACHE_PATH', storage_path('framework/blaze')),

];
