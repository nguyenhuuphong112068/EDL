<?php

namespace App\Providers;

use App\Services\Security\AccessControl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cache quyền trong một request
        $this->app->singleton(AccessControl::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFour();
        Carbon::setLocale('vi');
    }
}
