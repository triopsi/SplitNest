<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the saved account language, then the guest/session choice, then APP_LOCALE.
 */
class SetLocale
{
    /**
     * Resolve available locales on each request so account preferences survive a fresh session.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ([$request->user()?->locale, $request->session()->get('locale'), config('app.locale')] as $locale) {
            if (is_string($locale) && Locales::isAvailable($locale)) {
                app()->setLocale($locale);

                break;
            }
        }

        return $next($request);
    }
}
