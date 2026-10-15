<?php

namespace App\Providers\Filament;

use App\Filament\Auth\FirstUserRegister;
use App\Filament\Auth\InvitationRegister;
use App\Filament\Auth\Login;
use App\Filament\Auth\Profile;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsureHouseholdAdmin;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
            ->login(Login::class)
            ->registration(FirstUserRegister::class)
            ->passwordReset()
            ->profile(Profile::class, isSimple: false)
            ->brandName(fn (): string => config('app.name'))
            ->brandLogo(fn () => asset('brand/splitnest-logo-light.svg'))
            ->darkModeBrandLogo(fn () => asset('brand/splitnest-logo-dark.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => asset('brand/favicon.svg'))
            ->font('Inter', url: fn (): string => asset('fonts/filament/filament/inter/index.css'), provider: LocalFontProvider::class)
            ->maxContentWidth('full')
            ->renderHook('panels::head.end', fn () => view('filament.household-style'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.app-scripts'))
            ->renderHook('panels::footer', fn () => view('filament.version-footer'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.language-switcher'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.passkey-login'))
            ->colors([
                'primary' => Color::Teal,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('nav.groups.overview')),
                NavigationGroup::make(fn (): string => __('nav.groups.household')),
                NavigationGroup::make(fn (): string => __('nav.groups.system')),
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
                    ->label(__('users.menu'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => UserResource::getUrl())
                    ->visible(fn (): bool => UserResource::canCreate()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
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
