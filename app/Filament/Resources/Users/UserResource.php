<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Filament resource for user management: admins create logins here.
 *
 * Hidden from the sidebar; reachable through the profile dropdown. The own
 * account is protected against deletion and self-demotion.
 */
class UserResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    /**
     * Singular model label (translated).
     */
    public static function getModelLabel(): string
    {
        return __('users.label');
    }

    /**
     * Plural model label (translated).
     */
    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    /**
     * Define the create/edit form for a user account.
     *
     * The password is required on create only and left untouched when the
     * field stays empty on edit.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('users.fields.name'))->required()->maxLength(255),
            TextInput::make('email')->label(__('users.fields.email'))->email()->required()->maxLength(255)->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => strtolower(trim($state))),
            TextInput::make('password')->label(__('users.fields.password'))->password()->revealable()->autocomplete('new-password')->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(__('users.fields.password_hint')),
            Toggle::make('is_admin')->label(__('users.fields.is_admin'))
                ->helperText(__('users.fields.is_admin_hint'))
                ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id()),
        ]);
    }

    /**
     * Define the users table; the delete action is hidden for the own account.
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('users.table.name'))->searchable()->sortable(),
            TextColumn::make('email')->label(__('users.table.email'))->searchable(),
            IconColumn::make('is_admin')->label(__('users.table.admin'))->boolean(),
            TextColumn::make('created_at')->label(__('users.table.created'))->dateTime('d.m.Y')->sortable(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (User $record): bool => $record->getKey() !== auth()->id()),
        ]);
    }

    /**
     * Register the resource pages (single manage page).
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
