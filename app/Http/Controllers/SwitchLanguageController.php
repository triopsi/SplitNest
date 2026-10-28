<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stores the chosen UI language in the session and, when signed in, on the account.
 */
class SwitchLanguageController extends Controller
{
    /**
     * Handle the incoming request: remember the locale and go back.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locales::isAvailable($locale), 404);
        $request->user()?->forceFill(['locale' => $locale])->save();
        session()->put('locale', $locale);

        return redirect()->back();
    }
}
