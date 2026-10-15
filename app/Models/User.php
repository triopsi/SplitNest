<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Http\Middleware\EnsureHouseholdAdmin;
use App\Support\Gravatar;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

/**
 * A login account for the household app.
 *
 * Only admins may access the Filament panel (see also the
 * {@see EnsureHouseholdAdmin} middleware). Users can register passkeys for
 * passwordless login.
 */
#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasPasskeys
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithPasskeys, Notifiable;

    /**
     * Gravatar avatar for the panel user menu, when enabled in the settings.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return Gravatar::url($this->email);
    }

    /**
     * Determine whether the user may access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
