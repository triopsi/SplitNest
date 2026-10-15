<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTPS URL generation: with an https APP_URL every generated URL (assets
 * included) must be https, so TLS-terminating proxies cannot produce
 * mixed-content pages.
 */
class HttpsUrlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set an https APP_URL and re-run the provider boot.
     *
     * Expects asset() to generate https URLs.
     */
    public function test_https_app_url_forces_https_asset_urls(): void
    {
        config(['app.url' => 'https://splitnest.example']);
        (new AppServiceProvider($this->app))->boot();
        $this->assertStringStartsWith('https://', asset('fonts/example.css'));
    }

    /**
     * Set a plain http APP_URL and re-run the provider boot.
     *
     * Expects asset() to keep http URLs (local setups stay untouched).
     */
    public function test_http_app_url_keeps_http_asset_urls(): void
    {
        config(['app.url' => 'http://localhost:8080']);
        (new AppServiceProvider($this->app))->boot();
        $this->assertStringStartsWith('http://', asset('fonts/example.css'));
    }

    /**
     * Render the panel behind TLS termination and verify its configured font URL.
     */
    public function test_login_page_renders_https_font_url_with_https_app_url(): void
    {
        User::factory()->create();
        config(['app.url' => 'https://splitnest.example']);
        (new AppServiceProvider($this->app))->boot();

        $this->get('http://splitnest.example/admin/login')
            ->assertOk()
            ->assertSee('https://splitnest.example/fonts/filament/filament/inter/index.css', false)
            ->assertDontSee('http://splitnest.example/fonts/filament/filament/inter/index.css', false);
    }
}
