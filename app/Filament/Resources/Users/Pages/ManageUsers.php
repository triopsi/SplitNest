<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\InviteToHousehold;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

/**
 * Single-page CRUD screen for user accounts.
 */
class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Header actions: create a user directly or invite one by e-mail.
     *
     * The invite mails a one-time registration link (default 24 h valid);
     * invited members register themselves as admins of the household.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('invite')->label('Per E-Mail einladen')->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InviteToHousehold::class)->invite($data['email'], (int) auth()->id());
                        Notification::make()->success()->title('Einladung verschickt. Der Link ist '.config('app.invitation_ttl_hours').' Stunden gültig.')->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Einladung nicht möglich')->body(collect($e->errors())->flatten()->implode(' '))->send();
                    }
                }),
        ];
    }
}
