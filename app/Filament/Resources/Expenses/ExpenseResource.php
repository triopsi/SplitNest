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

/**
 * Filament resource for managing household expenses and their allocation.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    /**
     * Singular model label (translated).
     */
    public static function getModelLabel(): string
    {
        return __('expenses.label');
    }

    /**
     * Plural model label (translated).
     */
    public static function getPluralModelLabel(): string
    {
        return __('expenses.plural');
    }

    /**
     * Sidebar group (translated).
     */
    public static function getNavigationGroup(): string
    {
        return __('nav.groups.household');
    }

    /**
     * Define the create/edit form for an expense.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('expenses.sections.what'))
                    ->description(__('expenses.sections.what_desc'))
                    ->schema([
                        TextInput::make('name')->label(__('expenses.fields.name'))->placeholder(__('expenses.fields.name_placeholder'))->required()->maxLength(255)
                            ->helperText(__('expenses.fields.name_hint')),
                        Select::make('payer_id')->label(__('expenses.fields.payer'))->relationship('payer', 'name')->preload()->searchable()->required()->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($get('split_mode') === 'personal') {
                                    $set('shares', [['participant_id' => $state, 'percentage' => null]]);
                                }
                            })
                            ->helperText(__('expenses.fields.payer_hint')),
                    ]),
                Section::make(__('expenses.sections.schedule'))
                    ->description(__('expenses.sections.schedule_desc'))
                    ->schema([...ScheduleFields::make()]),
                Section::make(__('expenses.sections.split'))
                    ->description(__('expenses.sections.split_desc'))
                    ->schema([
                        Select::make('split_mode')->label(__('expenses.fields.split_mode'))->options(Expense::modeLabels())->default('percentage')->required()->live()
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                if ($state === 'personal') {
                                    $set('shares', [['participant_id' => $get('payer_id'), 'percentage' => null]]);
                                } elseif ($state === 'percentage') {
                                    $set('shares', collect($get('shares') ?? [])->map(fn (array $share): array => [...$share, 'percentage' => $share['percentage'] ?? '50'])->all());
                                }
                            })
                            ->helperText(__('expenses.fields.split_hint')),
                        Repeater::make('shares')->label(__('expenses.fields.shares'))->minItems(1)->required()->reorderable(false)
                            ->addActionLabel(__('expenses.fields.add_person'))
                            ->helperText(fn (Get $get): string => match ($get('split_mode')) {
                                'percentage' => __('expenses.share_hints.percentage'),
                                'income' => __('expenses.share_hints.income'),
                                'equal' => __('expenses.share_hints.equal'),
                                'personal' => __('expenses.share_hints.personal'),
                                default => __('expenses.share_hints.default'),
                            })
                            ->addable(fn (Get $get): bool => $get('split_mode') !== 'personal')
                            ->deletable(fn (Get $get): bool => $get('split_mode') !== 'personal')
                            ->maxItems(fn (Get $get) => $get('split_mode') === 'personal' ? 1 : 100)
                            ->default(fn () => Participant::orderBy('id')->limit(2)->get()->map(fn ($p) => ['participant_id' => $p->id, 'percentage' => '50'])->all())
                            ->schema([
                                Select::make('participant_id')->label(__('expenses.fields.participant'))->options(fn () => Participant::orderBy('name')->pluck('name', 'id'))
                                    ->searchable()->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TextInput::make('percentage')->label(__('expenses.fields.percentage'))->suffix('%')->default('50')
                                    ->visible(fn (Get $get) => $get('../../split_mode') === 'percentage')
                                    ->required(fn (Get $get) => $get('../../split_mode') === 'percentage')
                                    ->rules(['regex:/^\d{1,3}([.,]\d{1,2})?$/D'])->helperText(__('expenses.fields.percentage_hint')),
                            ])
                            ->rules([fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                if ($get('split_mode') === 'percentage') {
                                    try {
                                        $sum = array_sum(array_map(fn ($share) => Money::cents($share['percentage'] ?? '', 'shares'), $value));
                                        if ($sum !== 10000) {
                                            $fail(__('expenses.validation.sum_100'));
                                        }
                                    } catch (ValidationException) {
                                        $fail(__('expenses.fields.percentage_hint'));
                                    }
                                }
                            }]),
                    ]),
                Textarea::make('notes')->label(__('expenses.fields.notes'))->columnSpanFull()
                    ->helperText(__('expenses.fields.notes_hint')),
            ]);
    }

    /**
     * Define the expenses table (columns and record actions).
     */
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('expenses.table.name'))->searchable()->sortable()->toggleable(),
            TextColumn::make('payer.name')->label(__('expenses.table.payer'))->sortable()->html()->toggleable()
                ->formatStateUsing(fn (Expense $record): string => view('components.person', ['name' => $record->payer->name, 'email' => $record->payer->email])->render()),
            TextColumn::make('amount_cents')->label(__('expenses.table.amount'))->formatStateUsing(fn ($state) => Money::format($state))->sortable()->toggleable(),
            TextColumn::make('next_billing')->label(__('expenses.table.next_billing'))->toggleable()
                ->getStateUsing(fn (Expense $record): string => ($next = $record->nextBillingMonth(now()->format('Y-m')))
                    ? Carbon::createFromFormat('Y-m', $next)->format('m/Y') : '–'),
            TextColumn::make('split_mode')->label(__('expenses.table.split_mode'))->formatStateUsing(fn ($state) => Expense::modeLabels()[$state])->badge()->toggleable(),
            TextColumn::make('start_month')->label(__('expenses.table.from'))->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('end_month')->label(__('expenses.table.until'))->placeholder(__('schedule.unlimited'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('interval_months')->label(__('incomes.table.interval'))->formatStateUsing(fn ($state) => $state ? __('schedule.every_x_months', ['count' => $state]) : __('schedule.one_off'))->toggleable(),
        ])->filters([
            SelectFilter::make('payer')->relationship('payer', 'name')->label(__('expenses.filters.payer')),
            SelectFilter::make('split_mode')->label(__('expenses.filters.split_mode'))->options(Expense::modeLabels()),
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
