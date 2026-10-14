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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

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

    protected static ?int $navigationSort = 5;

    /**
     * Define the create/edit form for an expense.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Bezeichnung')->placeholder('Miete')->required()->maxLength(255),
            Select::make('payer_id')->label('Wer bezahlt die Rechnung?')->relationship('payer', 'name')->preload()->searchable()->required(),
            ...ScheduleFields::make(),
            Select::make('split_mode')->label('Aufteilung')->options(Expense::MODES)->default('percentage')->required()->live()
                ->helperText('Einkommensanteile beziehen sich nur auf die unten ausgewählten Teilnehmer.'),
            Repeater::make('shares')->label('Wer trägt die Kosten?')->columnSpanFull()->minItems(1)->required()->reorderable(false)
                ->maxItems(fn (Get $get) => $get('split_mode') === 'personal' ? 1 : 100)
                ->default(fn () => Participant::orderBy('id')->limit(2)->get()->map(fn ($p) => ['participant_id' => $p->id, 'percentage' => '50'])->all())
                ->schema([
                    Select::make('participant_id')->label('Teilnehmer')->options(fn () => Participant::orderBy('name')->pluck('name', 'id'))
                        ->searchable()->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    TextInput::make('percentage')->label('Anteil')->suffix('%')->default('50')
                        ->visible(fn (Get $get) => $get('../../split_mode') === 'percentage')
                        ->required(fn (Get $get) => $get('../../split_mode') === 'percentage')
                        ->rules(['regex:/^\d{1,3}([.,]\d{1,2})?$/D'])->helperText('Zusammen genau 100 %.'),
                ])->columns(2)
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
            Textarea::make('notes')->label('Notizen')->columnSpanFull(),
        ]);
    }

    /**
     * Define the expenses table (columns and record actions).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Bezeichnung')->searchable()->sortable(),
            TextColumn::make('payer.name')->label('Bezahler')->sortable(),
            TextColumn::make('amount_cents')->label('Betrag')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
            TextColumn::make('split_mode')->label('Aufteilung')->formatStateUsing(fn ($state) => Expense::MODES[$state])->badge(),
            TextColumn::make('start_month')->label('Ab')->sortable(),
            TextColumn::make('end_month')->label('Bis')->placeholder('Unbegrenzt'),
            TextColumn::make('interval_months')->label('Intervall')->formatStateUsing(fn ($state) => $state ? "Alle {$state} Monate" : 'Einmalig'),
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
