<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
//        $this->app->register(SanctumServiceProvider::class);
    }

    public function boot(): void
    {
        if (app()->environment('local')) {
            DB::listen(function (QueryExecuted $query) {
                Log::debug('DB Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                ]);
            });
        }
    }
}
