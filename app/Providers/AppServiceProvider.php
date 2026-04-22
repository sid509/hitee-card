<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

use Illuminate\Support\Facades\View;
use App\Models\SupportRequest;

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
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        // View Composer for Sidebar and Dashboard
        View::composer('*', function ($view) {
            if (auth()->check() && auth()->user()->hasRole('super-admin')) {
                $view->with('openSupportCount', SupportRequest::where('status', 'open')->count());
            }
        });
    }
}
