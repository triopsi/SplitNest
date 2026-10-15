<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;

/**
 * Stores the chosen UI language in the session and redirects back.
 */
class SwitchLanguageController extends Controller
{
    /**
     * Handle the incoming request: remember the locale and go back.
     */
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(Locales::isAvailable($locale), 404);
        session()->put('locale', $locale);

        return redirect()->back();
    }
}
