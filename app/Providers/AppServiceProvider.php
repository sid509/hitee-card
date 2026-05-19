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
use App\Models\CardApplication;

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

        // Override Config from Database Settings
        try {
            if (\Schema::hasTable('settings')) {
                $dbSettings = \App\Models\Setting::all()->pluck('value', 'key')->map(fn($v) => trim($v))->toArray();
                
                // Mail Config Override
                if (isset($dbSettings['mail_host'])) config(['mail.mailers.smtp.host' => $dbSettings['mail_host']]);
                if (isset($dbSettings['mail_port'])) config(['mail.mailers.smtp.port' => $dbSettings['mail_port']]);
                if (isset($dbSettings['mail_username'])) config(['mail.mailers.smtp.username' => $dbSettings['mail_username']]);
                if (isset($dbSettings['mail_password'])) config(['mail.mailers.smtp.password' => $dbSettings['mail_password']]);
                if (isset($dbSettings['mail_encryption'])) config(['mail.mailers.smtp.encryption' => $dbSettings['mail_encryption']]);
                if (isset($dbSettings['mail_from_address'])) config(['mail.from.address' => $dbSettings['mail_from_address']]);
                if (isset($dbSettings['mail_from_name'])) config(['mail.from.name' => $dbSettings['mail_from_name']]);

                // Services (Khalti, FB, Google)
                if (isset($dbSettings['khalti_secret_key'])) config(['services.khalti.secret_key' => $dbSettings['khalti_secret_key']]);
                if (isset($dbSettings['khalti_public_key'])) config(['services.khalti.public_key' => $dbSettings['khalti_public_key']]);
                if (isset($dbSettings['stripe_secret_key'])) config(['services.stripe.secret' => $dbSettings['stripe_secret_key']]);
                if (isset($dbSettings['stripe_publishable_key'])) config(['services.stripe.key' => $dbSettings['stripe_publishable_key']]);
                if (isset($dbSettings['stripe_currency'])) config(['services.stripe.currency' => $dbSettings['stripe_currency']]);
                if (isset($dbSettings['khalti_mode'])) config(['services.khalti.mode' => $dbSettings['khalti_mode']]);
                if (isset($dbSettings['facebook_client_id'])) config(['services.facebook.client_id' => $dbSettings['facebook_client_id']]);
                if (isset($dbSettings['facebook_client_secret'])) config(['services.facebook.client_secret' => $dbSettings['facebook_client_secret']]);
                if (isset($dbSettings['facebook_redirect_url'])) config(['services.facebook.redirect' => $dbSettings['facebook_redirect_url']]);
                if (isset($dbSettings['google_client_id'])) config(['services.google.client_id' => $dbSettings['google_client_id']]);
                if (isset($dbSettings['google_client_secret'])) config(['services.google.client_secret' => $dbSettings['google_client_secret']]);
                if (isset($dbSettings['google_redirect_url'])) config(['services.google.redirect' => $dbSettings['google_redirect_url']]);
                
                // AWS
                if (isset($dbSettings['aws_access_key_id'])) config(['filesystems.disks.s3.key' => $dbSettings['aws_access_key_id']]);
                if (isset($dbSettings['aws_secret_access_key'])) config(['filesystems.disks.s3.secret' => $dbSettings['aws_secret_access_key']]);
                if (isset($dbSettings['aws_default_region'])) config(['filesystems.disks.s3.region' => $dbSettings['aws_default_region']]);
                if (isset($dbSettings['aws_bucket'])) config(['filesystems.disks.s3.bucket' => $dbSettings['aws_bucket']]);
            }
        } catch (\Exception $e) {
            // Table might not exist yet during migration
        }

        // View Composer for Sidebar and Dashboard
        View::composer('*', function ($view) {
            if (auth()->check() && auth()->user()->hasRole('super-admin')) {
                $view->with('openSupportCount', SupportRequest::where('status', 'open')->count());
                $view->with('pendingCardApplicationsCount', CardApplication::where('status', 'pending')->count());
            }
        });
    }
}
