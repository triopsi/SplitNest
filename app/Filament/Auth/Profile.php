<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Profile page: account details plus passkey management.
 *
 * Passkeys are created, listed and deleted by the laravel-passkeys Livewire
 * component; every user may register several passkeys for passwordless login.
 */
class Profile extends EditProfile
{
    /**
     * Keep account editing and passkey management in separate, accessible tabs.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('users.edit_profile'))
                ->extraAttributes(['class' => 'sn-settings-tabs sn-profile-tabs'])
                ->tabs([
                    Tab::make(__('users.profile.account'))
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->schema(parent::content($schema)->getComponents()),
                    Tab::make(__('users.passkeys.heading'))
                        ->icon(Heroicon::OutlinedKey)
                        ->schema([
                            Section::make(__('users.passkeys.heading'))
                                ->description(__('users.passkeys.description'))
                                ->contained(false)
                                ->schema([
                                    Livewire::make('passkeys'),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * Align account labels beside the existing validated profile fields.
     */
    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema)->inlineLabel();

        foreach ($schema->getComponents() as $component) {
            if ($component instanceof TextInput) {
                $component->prefixIcon(match ($component->getName()) {
                    'name' => Heroicon::OutlinedUser,
                    'email' => Heroicon::OutlinedEnvelope,
                    default => Heroicon::OutlinedLockClosed,
                });
            }
        }

        return $schema;
    }

    /**
     * Give the standalone profile room for the tab bar and horizontal fields.
     */
    public function getMaxWidth(): Width|string|null
    {
        return Width::SevenExtraLarge;
    }
}
