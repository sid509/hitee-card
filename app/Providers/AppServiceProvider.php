<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', function (User $user) {
            return $user->hasRole('super-admin');
        });

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->components->addSecurityScheme(
                'bearer',
                SecurityScheme::http('bearer')
            );
        });
    }
}
