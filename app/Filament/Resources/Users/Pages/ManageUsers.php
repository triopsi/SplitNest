<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\InviteToHousehold;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;
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
            Action::make('invite')->label(__('users.invite.action'))->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('email')->label(__('users.fields.email'))->email()->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    try {
                        app(InviteToHousehold::class)->invite($data['email'], (int) Auth::id());
                        Notification::make()->success()->title(__('users.invite.success', ['hours' => config('app.invitation_ttl_hours')]))->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(__('users.invite.error'))->body(collect($e->errors())->flatten()->implode(' '))->send();
                    }
                }),
        ];
    }
}
