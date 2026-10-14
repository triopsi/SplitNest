<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * System settings page: household-wide switches such as Gravatar avatars.
 */
class SystemSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'Einstellungen';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.system-settings';

    /** Form state, keyed by field name. */
    public ?array $data = [];

    /**
     * Fill the form with the persisted settings.
     */
    public function mount(): void
    {
        $this->form->fill([
            'gravatar_enabled' => Settings::gravatarEnabled(),
            'mail_body' => Settings::mailBody(),
        ]);
    }

    /**
     * Define the settings form.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Avatare')
                ->description('Profilbilder anhand der hinterlegten E-Mail-Adresse anzeigen.')
                ->schema([
                    Toggle::make('gravatar_enabled')->label('Gravatar verwenden')
                        ->helperText('Wenn aktiv, erscheinen Gravatar-Bilder beim angemeldeten Benutzer oben rechts und überall dort, wo Teilnehmer mit E-Mail-Adresse genannt werden. Die Bilder werden von gravatar.com geladen.'),
                ]),
            Section::make('E-Mail beim Monatsabschluss')
                ->description('Beim Abschließen eines Monats erhalten alle Teilnehmer mit E-Mail-Adresse die Abrechnung als PDF und ihren Zahlungsplan per E-Mail.')
                ->schema([
                    Textarea::make('mail_body')->label('E-Mail-Text')->rows(10)->required()
                        ->helperText('Platzhalter: {name} = Teilnehmername, {monat} = Abrechnungsmonat, {ueberweisungen} = Liste der Zahlungen des Teilnehmers.'),
                ]),
        ])->statePath('data');
    }

    /**
     * Persist the settings.
     */
    public function save(): void
    {
        $state = $this->form->getState();
        Settings::set('gravatar_enabled', ($state['gravatar_enabled'] ?? false) ? '1' : '0');
        Settings::set('mail_body', $state['mail_body'] ?? Settings::DEFAULT_MAIL_BODY);
        Notification::make()->success()->title('Einstellungen gespeichert.')->send();
    }
}
