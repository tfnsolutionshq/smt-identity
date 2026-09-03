<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Integrations\Contracts\DepartmentIntegrationInterface;
use App\Integrations\Default\DefaultDepartmentIntegration;
use App\Integrations\Unizik\UnizikDepartmentIntegration;
use App\Integrations\Contracts\UserIntegrationInterface;
use App\Integrations\Default\DefaultUserIntegration;
use App\Integrations\Unizik\UnizikUserIntegration;

class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(DepartmentIntegrationInterface::class, function ($app) {
            $activeIntegration = config('services.integration.active', env('ACTIVE_INTEGRATION'));

            if ($activeIntegration === 'unizik') {
                return new UnizikDepartmentIntegration();
            }

            // Fallback to default local database behavior
            return new DefaultDepartmentIntegration();
        });

        $this->app->bind(UserIntegrationInterface::class, function ($app) {
            $activeIntegration = config('services.integration.active', env('ACTIVE_INTEGRATION'));

            if ($activeIntegration === 'unizik') {
                return new UnizikUserIntegration();
            }

            // Fallback to default local database behavior
            return new DefaultUserIntegration();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
