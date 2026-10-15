<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Profile page: account details plus passkey management.
 *
 * Passkeys are created, listed and deleted by the laravel-passkeys Livewire
 * component; every user may register several passkeys for passwordless login.
 */
class Profile extends EditProfile
{
    /**
     * Profile form plus the passkey management section.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...parent::content($schema)->getComponents(),
            Section::make(__('users.passkeys.heading'))
                ->description(__('users.passkeys.description'))
                ->schema([
                    Livewire::make('passkeys'),
                ]),
        ]);
    }
}
