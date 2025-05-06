<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'version' => '1.0.0',
        'documentation' => url('/swagger'),
        'framework' => 'Laravel',
        'framework_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'environment' => app()->environment(),
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'app_env' => config('app.env'),
        'app_locale' => config('app.locale'),
        'app_timezone' => config('app.timezone'),
    ]);
});
