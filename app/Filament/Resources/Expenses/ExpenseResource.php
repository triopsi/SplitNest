<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Support\ScheduleFields;
use App\Models\Expense;
use App\Models\Participant;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Filament resource for managing household expenses and their allocation.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $modelLabel = 'Kostenposition';

    protected static ?string $pluralModelLabel = 'Kosten';

    protected static ?string $recordTitleAttribute = 'name';

    protected static UnitEnum|string|null $navigationGroup = 'Haushalt';

    protected static ?int $navigationSort = 5;

    /**
     * Define the create/edit form for an expense.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Was wird gezahlt?')
                    ->description('Wofür ist die Kostenposition und wer bezahlt die Rechnung?')
                    ->schema([
                        TextInput::make('name')->label('Bezeichnung')->placeholder('z. B. Miete, Strom, Spotify')->required()->maxLength(255)
                            ->helperText('Wofür ist die Ausgabe? Ein kurzer Name genügt, z. B. „Miete“ oder „Internet“.'),
                        Select::make('payer_id')->label('Bezahlt von')->relationship('payer', 'name')->preload()->searchable()->required()->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($get('split_mode') === 'personal') {
                                    $set('shares', [['participant_id' => $state, 'percentage' => null]]);
                                }
                            })
                            ->helperText('Wer bezahlt die Rechnung bzw. von wessen Konto geht das Geld ab? Beispiel: Daniel überweist die Miete → „Daniel“ auswählen.'),
                    ]),
                Section::make('Wie viel und wie oft?')
                    ->description('Betrag, Rhythmus und Zeitraum der Kostenposition.')
                    ->schema([...ScheduleFields::make()]),
                Section::make('Wer trägt die Kosten?')
                    ->description('Lege fest, wie der Betrag auf die Teilnehmer verteilt wird.')
                    ->schema([
                        Select::make('split_mode')->label('Aufteilung')->options(Expense::MODES)->default('percentage')->required()->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($state === 'personal') {
                                    $set('shares', [['participant_id' => $get('payer_id'), 'percentage' => null]]);
                                } elseif ($state === 'percentage') {
                                    $set('shares', collect($get('shares') ?? [])->map(fn (array $share): array => [...$share, 'percentage' => $share['percentage'] ?? '50'])->all());
                                }
                            })
                            ->helperText('Wie wird der Betrag aufgeteilt? Beispiele für 100 €: Feste Prozente — ihr legt die Anteile selbst fest, z. B. Daniel 60 %, Svenja 40 %. Nach Einkommen — wer mehr verdient, zahlt mehr: bei 3.000 € und 2.000 € Einkommen zahlt Daniel 60 €, Svenja 40 €. Gleiche Anteile — jede Person zahlt gleich viel, also je 50 €. Persönlich — eine Person trägt alles, z. B. Svenjas Handyvertrag.'),
                        Repeater::make('shares')->label('Personen')->minItems(1)->required()->reorderable(false)
                            ->addActionLabel('Weitere Person hinzufügen')
                            ->helperText(fn (Get $get): string => match ($get('split_mode')) {
                                'percentage' => 'Wähle alle Personen aus, die sich beteiligen, und lege ihre Anteile in Prozent fest. Zusammen muss es genau 100 % ergeben, z. B. 60 % + 40 %.',
                                'income' => 'Wähle alle Personen aus, die sich beteiligen. Die Anteile berechnen sich automatisch aus den Einkünften: Wer mehr verdient, trägt mehr.',
                                'equal' => 'Wähle alle Personen aus, die sich beteiligen. Der Betrag wird gleichmäßig auf alle verteilt.',
                                'personal' => 'Diese eine Person trägt die Kosten allein — voreingestellt ist der Bezahler.',
                                default => 'Wer beteiligt sich an den Kosten?',
                            })
                            ->addable(fn (Get $get): bool => $get('split_mode') !== 'personal')
                            ->deletable(fn (Get $get): bool => $get('split_mode') !== 'personal')
                            ->maxItems(fn (Get $get) => $get('split_mode') === 'personal' ? 1 : 100)
                            ->default(fn () => Participant::orderBy('id')->limit(2)->get()->map(fn ($p) => ['participant_id' => $p->id, 'percentage' => '50'])->all())
                            ->schema([
                                Select::make('participant_id')->label('Teilnehmer')->options(fn () => Participant::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('percentage')->label('Anteil')->suffix('%')->default('50')
                                    ->visible(fn (Get $get) => $get('../../split_mode') === 'percentage')
                                    ->required(fn (Get $get) => $get('../../split_mode') === 'percentage')
                                    ->rules(['regex:/^\d{1,3}([.,]\d{1,2})?$/D'])->helperText('Anteil dieser Person in Prozent, z. B. 60. Alle Anteile zusammen müssen genau 100 % ergeben.'),
                            ])
                            ->rules([fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                if ($get('split_mode') === 'percentage') {
                                    try {
                                        $sum = array_sum(array_map(fn ($share) => Money::cents($share['percentage'] ?? '', 'shares'), $value));
                                        if ($sum !== 10000) {
                                            $fail('Die Anteile müssen zusammen genau 100 % ergeben.');
                                        }
                                    } catch (ValidationException) {
                                        $fail('Bitte gültige Prozentwerte eintragen.');
                                    }
                                }
                            }]),
                    ]),
                Textarea::make('notes')->label('Notizen')->columnSpanFull()
                    ->helperText('Optional, z. B. Vertragslaufzeit, Kündigungsfrist oder wer die Lastschrift eingerichtet hat.'),
            ]);
    }

    /**
     * Define the expenses table (columns and record actions).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Bezeichnung')->searchable()->sortable()->toggleable(),
            TextColumn::make('payer.name')->label('Bezahler')->sortable()->html()->toggleable()
                ->formatStateUsing(fn (Expense $record): string => view('components.person', ['name' => $record->payer->name, 'email' => $record->payer->email])->render()),
            TextColumn::make('amount_cents')->label('Betrag')->formatStateUsing(fn ($state) => Money::format($state))->sortable()->toggleable(),
            TextColumn::make('next_billing')->label('Nächste Abrechnung')->toggleable()
                ->getStateUsing(fn (Expense $record): string => ($next = $record->nextBillingMonth(now()->format('Y-m')))
                    ? Carbon::createFromFormat('Y-m', $next)->format('m/Y') : '–'),
            TextColumn::make('split_mode')->label('Aufteilung')->formatStateUsing(fn ($state) => Expense::MODES[$state])->badge()->toggleable(),
            TextColumn::make('start_month')->label('Ab')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('end_month')->label('Bis')->placeholder('Unbegrenzt')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('interval_months')->label('Intervall')->formatStateUsing(fn ($state) => $state ? "Alle {$state} Monate" : 'Einmalig')->toggleable(),
        ])->filters([
            SelectFilter::make('payer')->relationship('payer', 'name')->label('Bezahler'),
            SelectFilter::make('split_mode')->label('Aufteilung')->options(Expense::MODES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /**
     * Register the resource pages (single manage page).
     *
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }
}
