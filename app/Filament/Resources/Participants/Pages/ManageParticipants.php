<?php

namespace App\Filament\Resources\Participants\Pages;

use App\Filament\Resources\Participants\ParticipantResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * Single-page CRUD screen for participants.
 */
class ManageParticipants extends ManageRecords
{
    protected static string $resource = ParticipantResource::class;

    /** Explain participant identities, statement delivery and visibility before the list. */
    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            View::make('filament.participants-help'),
            ...$schema->getComponents(),
        ]);
    }

    /**
     * Header actions: create a new participant.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
