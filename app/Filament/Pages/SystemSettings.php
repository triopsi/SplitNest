<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * System settings page: household-wide switches such as Gravatar avatars.
 */
class SystemSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.system-settings';

    /** Form state, keyed by field name. */
    public ?array $data = [];

    /**
     * Page title (translated).
     */
    public function getTitle(): string
    {
        return __('settings.title');
    }

    /**
     * Navigation label (translated).
     */
    public static function getNavigationLabel(): string
    {
        return __('settings.title');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.system');
    }

    /**
     * Fill the form with the persisted settings.
     */
    public function mount(): void
    {
        $this->form->fill([
            'gravatar_enabled' => Settings::gravatarEnabled(),
            'gravatar_style' => Settings::gravatarStyle(),
            'mail_body' => Settings::mailBody(),
        ]);
    }

    /**
     * Define the settings form.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('settings.title'))
                ->extraAttributes(['class' => 'sn-settings-tabs'])
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('settings.avatars'))
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->schema([
                            Section::make(__('settings.avatars'))
                                ->contained(false)
                                ->description(__('settings.avatars_desc'))
                                ->schema([
                                    Toggle::make('gravatar_enabled')->label(__('settings.gravatar'))->live()
                                        ->helperText(__('settings.gravatar_hint')),
                                    Select::make('gravatar_style')->label(__('settings.gravatar_style'))
                                        ->options(__('settings.gravatar_styles'))->required()
                                        ->visible(fn (Get $get): bool => (bool) $get('gravatar_enabled'))
                                        ->helperText(__('settings.gravatar_style_hint')),
                                ]),
                        ]),
                    Tab::make(__('settings.closing_mail'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->schema([
                            Section::make(__('settings.closing_mail'))
                                ->contained(false)
                                ->schema([
                                    Textarea::make('mail_body')->label(__('settings.mail_body'))->rows(10)->required()
                                        ->helperText(__('settings.mail_body_hint')),
                                ]),
                        ]),
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
        Settings::set('gravatar_style', $state['gravatar_style'] ?? Settings::gravatarStyle());
        Settings::set('mail_body', $state['mail_body'] ?? __('mail.default_body'));
        Notification::make()->success()->title(__('settings.saved'))->send();
    }
}
