<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'Benutzer';

    protected static ?string $pluralModelLabel = 'Benutzer';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(255)->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => strtolower(trim($state))),
            TextInput::make('password')->label('Passwort')->password()->revealable()->autocomplete('new-password')->minLength(12)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Mindestens 12 Zeichen. Beim Bearbeiten leer lassen, um das Passwort zu behalten.'),
            Toggle::make('is_admin')->label('Administrator')
                ->helperText('Nur Administratoren dürfen sich einloggen und den Haushalt verwalten.')
                ->disabled(fn (?User $record): bool => $record?->getKey() === auth()->id()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->label('E-Mail')->searchable(),
            IconColumn::make('is_admin')->label('Admin')->boolean(),
            TextColumn::make('created_at')->label('Erstellt')->dateTime('d.m.Y')->sortable(),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->visible(fn (User $record): bool => $record->getKey() !== auth()->id()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
