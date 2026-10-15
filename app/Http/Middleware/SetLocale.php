<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the UI language chosen in the language switcher (stored in the
 * session) on top of the APP_LOCALE default.
 */
class SetLocale
{
    /**
     * Set the application locale from the session when it holds a valid locale.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');
        if (is_string($locale) && Locales::isAvailable($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
