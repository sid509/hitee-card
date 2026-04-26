<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetAppLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->header('x-app-lang', 'en');

        // Project uses 'ne' directory for Nepali
        if (in_array($lang, ['ne', 'np'])) {
            App::setLocale('ne');
        } else {
            App::setLocale('en');
        }

        return $next($request);
    }
}
