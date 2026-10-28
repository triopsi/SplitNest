<?php

namespace Tests\Feature;

use App\Filament\Pages\Settlement;
use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Models\User;
use App\Services\CloseMonth;
use App\Support\MonthlyDashboardLayout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Personal monthly layouts: persistence, account isolation, validation and PDF independence.
 */
class MonthlyDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Activate the Filament panel used by layout requests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * Create a valid rearrangement with swapped statistics and two half-width widgets.
     *
     * @return list<array{id: string, x: int, y: int, w: int, h: int}>
     */
    private function personalLayout(): array
    {
        $layout = MonthlyDashboardLayout::defaults();
        $layout[0]['x'] = 8;
        $layout[2]['x'] = 0;
        $layout[3]['w'] = 6;
        $layout[4] = ['id' => 'transfers', 'x' => 6, 'y' => 3, 'w' => 6, 'h' => 7];

        return $layout;
    }

    /**
     * Open the dashboard without personal preferences.
     *
     * Expects all seven widgets in the default geometry and the hero excluded from the catalog.
     */
    public function test_new_user_receives_the_standard_layout_without_saving_it(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $page = Livewire::test(Settlement::class);

        $this->assertSame(MonthlyDashboardLayout::defaults(), $page->instance()->dashboardLayout());
        $this->assertNull($user->fresh()->monthly_dashboard_layout);
        $this->assertArrayNotHasKey('hero', MonthlyDashboardLayout::widgets());
    }

    /**
     * Save personal positions and dimensions, then reload another month.
     *
     * Expects persisted geometry to be loaded for the same account across requests and months.
     */
    public function test_layout_persists_across_page_loads_and_selected_months(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        Livewire::test(Settlement::class, ['month' => '2027-01'])
            ->call('saveDashboardLayout', $this->personalLayout())
            ->assertHasNoErrors();

        $saved = $user->fresh()->monthly_dashboard_layout;
        $this->assertSame('remaining', $saved[0]['id']);
        $this->assertSame(6, collect($saved)->firstWhere('id', 'shares')['w']);
        $this->assertSame(7, collect($saved)->firstWhere('id', 'transfers')['h']);
        $page = Livewire::test(Settlement::class, ['month' => '2027-02']);
        $this->assertSame($saved, $page->instance()->dashboardLayout());
    }

    /**
     * Save and reset layouts while switching between two administrator accounts.
     *
     * Expects each operation to affect only the authenticated account's preferences.
     */
    public function test_layouts_and_reset_are_isolated_between_users(): void
    {
        $first = User::factory()->create(['is_admin' => true]);
        $second = User::factory()->create(['is_admin' => true]);
        $this->actingAs($first);
        Livewire::test(Settlement::class)->call('saveDashboardLayout', $this->personalLayout());
        $firstLayout = $first->fresh()->monthly_dashboard_layout;

        $this->actingAs($second);
        $page = Livewire::test(Settlement::class);
        $this->assertSame(MonthlyDashboardLayout::defaults(), $page->instance()->dashboardLayout());
        $page->call('saveDashboardLayout', MonthlyDashboardLayout::defaults());
        $page->call('resetDashboardLayout')->assertHasNoErrors();

        $this->assertNull($second->fresh()->monthly_dashboard_layout);
        $this->assertSame($firstLayout, $first->fresh()->monthly_dashboard_layout);
    }

    /**
     * Supply malformed geometry and attempts to change the catalog or owner.
     *
     * @return array<string, array{list<array<string, mixed>>}>
     */
    public static function invalidLayouts(): array
    {
        $default = MonthlyDashboardLayout::defaults();
        $unknown = $default;
        $unknown[0]['id'] = 'hero';
        $duplicate = $default;
        $duplicate[0]['id'] = 'expenses';
        $outside = $default;
        $outside[2]['w'] = 5;
        $small = $default;
        $small[3]['w'] = 3;
        $short = $default;
        $short[3]['h'] = 2;
        $overlap = $default;
        $overlap[1]['x'] = 0;
        $negative = $default;
        $negative[0]['y'] = -1;
        $huge = $default;
        $huge[6]['y'] = 119;
        $fractional = $default;
        $fractional[0]['x'] = 0.5;
        $owner = $default;
        $owner[0]['user_id'] = 999;
        $component = $default;
        $component[0]['widget'] = 'App\\Filament\\Pages\\Backup';

        return [
            'unknown widget' => [$unknown], 'duplicate widget' => [$duplicate],
            'missing widget' => [array_slice($default, 1)], 'outside grid' => [$outside],
            'too narrow' => [$small], 'too short' => [$short], 'overlapping' => [$overlap],
            'negative position' => [$negative], 'unbounded position' => [$huge],
            'fractional position' => [$fractional], 'owner injection' => [$owner],
            'component injection' => [$component],
        ];
    }

    /**
     * Submit invalid layout data while an account already has saved preferences.
     *
     * Expects validation errors without overwriting the existing personal layout.
     *
     * @param  list<array<string, mixed>>  $layout
     */
    #[DataProvider('invalidLayouts')]
    public function test_invalid_layouts_cannot_overwrite_saved_preferences(array $layout): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->forceFill(['monthly_dashboard_layout' => MonthlyDashboardLayout::defaults()])->save();
        $this->actingAs($user);

        Livewire::test(Settlement::class)->call('saveDashboardLayout', $layout)->assertHasErrors();

        $this->assertSame(MonthlyDashboardLayout::defaults(), $user->fresh()->monthly_dashboard_layout);
    }

    /**
     * Load preferences that no longer match the widget catalog.
     *
     * Expects a complete usable default layout instead of a broken or partial dashboard.
     */
    public function test_invalid_stored_preferences_fall_back_to_the_default_layout(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->forceFill(['monthly_dashboard_layout' => [['id' => 'removed-widget']]])->save();
        $this->actingAs($user);

        $page = Livewire::test(Settlement::class);

        $this->assertSame(MonthlyDashboardLayout::defaults(), $page->instance()->dashboardLayout());
    }

    /** Expand old short payment widgets and shift following rows without resetting personal widths. */
    public function test_short_saved_transfer_widgets_expand_without_overlapping_the_next_rows(): void
    {
        $legacy = MonthlyDashboardLayout::defaults();
        $legacy[0]['x'] = 8;
        $legacy[2]['x'] = 0;
        $legacy[4]['h'] = 3;
        $legacy[5]['y'] = 13;
        $legacy[6]['y'] = 18;
        $legacy[7]['y'] = 26;

        $layout = MonthlyDashboardLayout::resolve($legacy);
        $widgets = collect($layout)->keyBy('id');

        $this->assertSame(8, $widgets['income']['x']);
        $this->assertSame(0, $widgets['remaining']['x']);
        $this->assertSame(5, $widgets['transfers']['h']);
        $this->assertSame(15, $widgets['participants']['y']);
        $this->assertSame(20, $widgets['costs']['y']);
        $this->assertSame(28, $widgets['budgets']['y']);
        $this->assertSame($layout, MonthlyDashboardLayout::validate($layout));
    }

    /**
     * Revoke administrator access before an existing page attempts to save or reset.
     *
     * Expects both requests to be forbidden and no personal preferences to be written.
     */
    public function test_revoked_admin_cannot_save_or_reset_layouts(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        $page = Livewire::test(Settlement::class);
        $user->update(['is_admin' => false]);

        $page->call('saveDashboardLayout', $this->personalLayout())->assertForbidden();
        Livewire::test(Settlement::class)->call('resetDashboardLayout')->assertForbidden();

        $this->assertNull($user->fresh()->monthly_dashboard_layout);
    }

    /**
     * Change the layout of a closed month and download its PDF statement again.
     *
     * Expects an unchanged frozen snapshot and a successful PDF download.
     */
    public function test_layout_changes_do_not_modify_monthly_snapshots_or_pdf_generation(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Participant::create(['name' => 'Alex']);
        $this->actingAs($user);
        Mail::fake();
        $closing = app(CloseMonth::class)->close('2027-01', $user->id);
        $snapshot = $closing->snapshot;

        Livewire::test(Settlement::class, ['month' => '2027-01'])
            ->call('saveDashboardLayout', $this->personalLayout())->assertHasNoErrors();

        $this->assertSame($snapshot, $closing->fresh()->snapshot);
        $this->assertDatabaseCount(MonthlyClosing::class, 1);
        $this->get(route('closings.pdf', $closing))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
