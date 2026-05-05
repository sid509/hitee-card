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
        ])->routes(function ($route) {
            $controller = $route->getAction('controller');
            return is_string($controller) && str_starts_with($controller, 'App\Http\Controllers\Api\Merchant\\');
        })->afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        })->expose('docs/merchant', 'docs/merchant.json');

        // 2. Customer API Documentation
        Scramble::registerApi('customer', [
            'api_path' => 'api',
            'ui' => [
                'title' => 'Customer API Documentation',
            ],
        ])->routes(function ($route) {
            $controller = $route->getAction('controller');
            $uri = $route->uri();

            // 1. Must be in Customer namespace
            $isCustomerNamespace = is_string($controller) && str_starts_with($controller, 'App\Http\Controllers\Api\Customer\\');
            
            // 2. Must NOT be a merchant route
            $isMerchantRoute = str_starts_with($uri, 'api/merchant');

            return $isCustomerNamespace && !$isMerchantRoute;
        })->afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        })->expose('docs/customer', 'docs/customer.json');

        // Disable the default 'api' documentation
        Scramble::configure('default')->expose(false);

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
