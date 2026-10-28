<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use App\Models\Participant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Expense schedules entered through the form and displayed in the costs table.
 */
class ExpenseScheduleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Save a half-yearly expense and display the next month on its actual schedule.
     */
    #[DataProvider('halfYearlyTableSchedules')]
    public function test_half_yearly_expense_form_and_next_billing_column(string $today, string $start, string $expected, string $locale): void
    {
        app()->setLocale($locale);
        $this->travelTo($today.' 12:00:00');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $payer = Participant::create(['name' => 'Example participant']);

        $table = Livewire::test(ManageExpenses::class)
            ->callAction('create', data: [
                'name' => 'Half-yearly insurance',
                'payer_id' => $payer->id,
                'amount' => '120.00',
                'split_mode' => 'personal',
                'shares' => [['participant_id' => $payer->id]],
                'start_month' => $start,
                'interval_months' => 6,
            ])
            ->assertHasNoActionErrors();

        $expense = Expense::sole();
        $this->assertSame($start, $expense->start_month);
        $this->assertSame(6, $expense->interval_months);
        $table->assertTableColumnStateSet('next_billing', $expected, $expense);
    }

    /**
     * Cover the reported January start and formatting a short month on the 31st.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function halfYearlyTableSchedules(): array
    {
        return [
            'january start viewed in october' => ['2026-10-16', '2026-01', 'January 2027', 'en'],
            'february viewed on october 31' => ['2026-10-31', '2026-02', 'February 2027', 'en'],
            'german month names' => ['2026-10-28', '2026-10', 'Oktober 2026', 'de'],
        ];
    }
}
