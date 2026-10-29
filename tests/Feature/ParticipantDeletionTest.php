<?php

namespace Tests\Feature;

use App\Filament\Resources\Participants\Pages\ManageParticipants;
use App\Models\Expense;
use App\Models\Income;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Unused identities can be removed while current assignments and historical reports stay valid. */
class ParticipantDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** Both relational and JSON references prevent deletion through the UI and model. */
    #[DataProvider('linkedRecords')]
    public function test_linked_participants_cannot_be_deleted(string $reference, string $message): void
    {
        $person = Participant::create(['name' => 'Participant']);
        $other = Participant::create(['name' => 'Other participant']);
        if (in_array($reference, ['recipient', 'sender'], true)) {
            Income::create(['name' => 'Receipt', 'participant_id' => $reference === 'recipient' ? $person->id : $other->id, 'sender_id' => $reference === 'sender' ? $person->id : null, 'amount_cents' => 100, 'start_month' => '2027-01', 'interval_months' => 1]);
        } elseif ($reference === 'closing') {
            MonthlyClosing::create(['month' => '2027-01', 'closed_at' => now(), 'snapshot' => ['participants' => [['id' => $person->id, 'name' => $person->name]]]]);
        } else {
            Expense::create(['name' => 'Bill', 'payer_id' => $reference === 'payer' ? $person->id : $other->id, 'amount_cents' => 100, 'start_month' => '2027-01', 'interval_months' => 1, 'split_mode' => 'personal', 'shares' => [['participant_id' => $reference === 'string share' ? (string) $person->id : ($reference === 'payer' ? $other->id : $person->id)]]]);
        }
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageParticipants::class)->assertTableActionDisabled('delete', $person);
        try {
            $person->delete();
            $this->fail('Linked participant deleted');
        } catch (ValidationException $exception) {
            $this->assertSame(__($message), $exception->errors()['participant'][0]);
        }

        $this->assertModelExists($person);
        $this->assertModelExists($other);
    }

    /** Sources of references include the JSON shares that have no foreign-key constraint.
     * @return array<string, array{string, string}>
     */
    public static function linkedRecords(): array
    {
        return [
            'income recipient' => ['recipient', 'participants.delete_has_incomes'],
            'income sender' => ['sender', 'participants.delete_has_incomes'],
            'expense payer' => ['payer', 'participants.delete_has_expenses'],
            'expense share only' => ['share', 'participants.delete_has_expenses'],
            'JSON string participant ID' => ['string share', 'participants.delete_has_expenses'],
            'historical snapshot only' => ['closing', 'participants.delete_has_closings'],
        ];
    }

    /** Removing an unused participant never removes an independent login account with the same e-mail. */
    public function test_unused_participant_deletion_keeps_the_login_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $person = Participant::create(['name' => 'Unused participant', 'email' => $admin->email]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageParticipants::class)->assertTableActionEnabled('delete', $person)
            ->callTableAction('delete', $person)->assertHasNoTableActionErrors();

        $this->assertModelMissing($person);
        $this->assertModelExists($admin);
        $this->assertAuthenticatedAs($admin);
    }

    /** Non-admin accounts still have no access to participant management or its deletion actions. */
    public function test_regular_accounts_cannot_access_participant_management(): void
    {
        $person = Participant::create(['name' => 'Participant']);
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/participants')->assertForbidden();

        $this->assertModelExists($person);
    }
}
