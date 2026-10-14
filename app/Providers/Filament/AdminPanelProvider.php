<?php

namespace App\Providers\Filament;

use App\Filament\Auth\InvitationRegister;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsureHouseholdAdmin;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Configures the "admin" Filament panel (the household back office).
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Build the panel: branding, discovery, user menu and middleware stack.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->brandName(fn (): string => config('app.name'))
            ->brandLogo(fn () => asset('brand/splitnest-logo-light.svg'))
            ->darkModeBrandLogo(fn () => asset('brand/splitnest-logo-dark.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => asset('favicon.svg'))
            ->font('Inter', url: asset('fonts/filament/filament/inter/index.css'), provider: LocalFontProvider::class)
            ->maxContentWidth('full')
            ->renderHook('panels::head.end', fn () => view('filament.household-style'))
            ->renderHook('panels::footer', fn () => view('filament.version-footer'))
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->routes(fn () => Route::get('/einladung/{token}', InvitationRegister::class)->name('invitation'))
            ->userMenuItems([
                Action::make('users')
                    ->label('Benutzer')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => UserResource::getUrl())
                    ->visible(fn (): bool => UserResource::canCreate()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureHouseholdAdmin::class,
            ], isPersistent: true);
    }
}
