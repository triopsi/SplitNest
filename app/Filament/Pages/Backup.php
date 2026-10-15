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

/**
 * Backup page: export the whole household as a ZIP download and restore it
 * from a backup file (replaces all existing data).
 */
class Backup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.backup';

    /**
     * Page title (translated).
     */
    public function getTitle(): string
    {
        return __('backup.title');
    }

    /**
     * Navigation label (translated).
     */
    public static function getNavigationLabel(): string
    {
        return __('backup.title');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.system');
    }

    /**
     * Export action: downloads all household data as a ZIP file.
     */
    public function exportAction(): Action
    {
        return Action::make('export')->label(__('backup.export'))->icon('heroicon-o-arrow-down-tray')
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
        return Action::make('import')->label(__('backup.import'))->icon('heroicon-o-arrow-up-tray')->color('danger')
            ->requiresConfirmation()->modalHeading(__('backup.import_heading'))
            ->modalDescription(__('backup.import_description'))
            ->form([
                FileUpload::make('file')->label(__('backup.file'))->disk('local')->directory('backup-uploads')
                    ->rules(['extensions:zip'])->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(HouseholdBackup::class)->importFromZip(Storage::disk('local')->path($data['file']));
                    Notification::make()->success()->title(__('backup.imported'))->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(__('backup.import_failed'))
                        ->body(collect($e->errors())->flatten()->implode(' '))->send();
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }
            });
    }
}
