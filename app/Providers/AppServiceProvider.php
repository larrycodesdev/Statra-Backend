<?php

namespace App\Providers;

use App\Services\RiskCalculatorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RiskCalculatorService::class);
    }

    public function boot(): void
    {
        //
    }
}
