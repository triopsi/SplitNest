<?php

namespace App\Filament\Pages;

use App\Services\HouseholdBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Backup page: export the whole household as a ZIP download and restore it
 * from a backup file (replaces all existing data).
 */
class Backup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $navigationLabel = 'Sicherung';

    protected static ?string $title = 'Sicherung';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.backup';

    /**
     * Export action: downloads all household data as a ZIP file.
     */
    public function exportAction(): Action
    {
        return Action::make('export')->label('Backup herunterladen')->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                $path = tempnam(sys_get_temp_dir(), 'splitnest-backup-');
                app(HouseholdBackup::class)->exportToZip($path);

                return response()->download($path, 'splitnest-backup-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
            });
    }

    /**
     * Import action: uploads a backup ZIP and replaces all household data with it.
     */
    public function importAction(): Action
    {
        return Action::make('import')->label('Backup einspielen')->icon('heroicon-o-arrow-up-tray')->color('danger')
            ->requiresConfirmation()->modalHeading('Backup einspielen und alle Daten ersetzen?')
            ->modalDescription('Alle aktuell gespeicherten Teilnehmer, Einkünfte, Kosten und Monatsabschlüsse werden durch den Inhalt der Datei ersetzt. Das kann nicht rückgängig gemacht werden.')
            ->form([
                FileUpload::make('file')->label('Backup-Datei (.zip)')->disk('local')->directory('backup-uploads')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(HouseholdBackup::class)->importFromZip(Storage::disk('local')->path($data['file']));
                    Notification::make()->success()->title('Backup eingespielt. Alle Daten wurden ersetzt.')->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Import fehlgeschlagen')
                        ->body(collect($e->errors())->flatten()->implode(' '))->send();
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }
            });
    }
}
