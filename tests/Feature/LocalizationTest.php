<?php

namespace Tests\Feature;

use App\Filament\Pages\Settlement;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Localization: English is the base language, German ships as a full
 * translation; the locale is chosen via APP_LOCALE.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Read translated UI strings in both shipped locales.
     *
     * Expects English by default and the German string after switching to de.
     */
    public function test_english_is_the_base_and_german_switches(): void
    {
        $this->assertSame('en', config('app.locale'));
        $this->assertSame('Monthly overview', __('settlement.title'));
        $this->assertSame('Overview', Settlement::getNavigationGroup());

        App::setLocale('de');
        $this->assertSame('Monatsübersicht', __('settlement.title'));
        $this->assertSame('Übersicht', Settlement::getNavigationGroup());
        $this->assertSame('Feste Prozente', Expense::modeLabels()['percentage']);
    }

    /**
     * Compare every shipped locale against the English base translation.
     *
     * Expects every locale directory to contain the same groups with the same
     * keys (recursively), so contributed translations cannot ship with gaps.
     */
    public function test_all_locales_cover_the_english_keys(): void
    {
        $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
            return collect($array)->mapWithKeys(fn ($value, $key) => is_array($value)
                ? $flatten($value, $prefix.$key.'.')
                : [$prefix.$key => $value])->all();
        };
        foreach (glob(base_path('lang/*'), GLOB_ONLYDIR) as $dir) {
            $locale = basename($dir);
            if ($locale === 'en') {
                continue;
            }
            foreach (glob(base_path('lang/en/*.php')) as $enFile) {
                $group = basename($enFile);
                $localeFile = $dir.'/'.$group;
                $this->assertFileExists($localeFile, "Missing {$locale} group {$group}");
                $this->assertSame(array_keys($flatten(require $enFile)), array_keys($flatten(require $localeFile)), "Key mismatch in {$locale}/{$group}");
            }
        }
    }
}
