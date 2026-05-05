<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Str;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class ScrambleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Merchant API Documentation
        Scramble::registerApi('merchant', [
            'api_path' => 'api/merchant',
            'ui' => [
                'title' => 'Merchant API Documentation',
            ],
        ])->expose('docs/merchant', 'docs/merchant.json');

        // 2. Customer API Documentation
        Scramble::registerApi('customer', [
            'api_path' => 'api',
            'routes' => function ($route) {
                // Include all /api routes EXCEPT /api/merchant
                return Str::startsWith($route->uri, 'api/') && !Str::startsWith($route->uri, 'api/merchant');
            },
            'ui' => [
                'title' => 'Customer API Documentation',
            ],
        ])->expose('docs/customer', 'docs/customer.json');

        // Disable the default 'api' documentation
        Scramble::configure('default')->expose(false);

        // Global configuration for both
        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        Scramble::configure()
            ->withOperationTransformers(function (\Dedoc\Scramble\Support\Generator\Operation $operation) {
                $operation->addParameters([
                    \Dedoc\Scramble\Support\Generator\Parameter::make('x-app-lang', 'header')
                        ->setSchema(\Dedoc\Scramble\Support\Generator\Schema::fromType(
                            (new \Dedoc\Scramble\Support\Generator\Types\StringType)->default('en')
                        ))
                        ->description('Application language preference. Use "en" for English, "ne" or "np" for Nepali.')
                ]);
            });
    }
}
