<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Follow the language the user picked in the app.
 *
 * The frontend keeps its own translations, so most of the interface switched
 * language on its own — but anything the server writes (printed receipts and
 * statements, validation messages) stayed in the application default. The
 * frontend now sends its chosen language on every request and this applies it.
 */
class SetLocale
{
    private const SUPPORTED = ['en', 'sw'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->header('X-Lang')
            ?: substr((string) $request->header('Accept-Language'), 0, 2);

        $locale = strtolower(trim((string) $requested));

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
