<?php

namespace App\Listeners;

use App\Events\MonthClosed;
use App\Mail\MonthClosedMail;
use App\Models\Participant;
use App\Services\ClosingReportPdf;
use App\Support\Money;
use App\Support\Month;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

/**
 * Sends every participant with an e-mail address the closing summary:
 * the frozen PDF statement plus their own balancing transfers.
 */
class SendClosingSummaryEmails
{
    /** Resolve the renderer shared by delivery and recipient previews. */
    public function __construct(private ClosingReportPdf $pdf) {}

    /**
     * Handle the event.
     */
    public function handle(MonthClosed $event): void
    {
        $closing = $event->closing;
        $report = $closing->snapshot;
        if (! $report) {
            return;
        }
        $names = array_column($report['participants'], 'name', 'id');
        $monthName = Month::format($closing->month);
        $fullPdfData = null;

        foreach (Participant::activeInMonth($closing->month)->whereNotNull('email')->where('email', '!=', '')->get() as $participant) {
            $pdfData = $participant->only_own_report
                ? $this->pdf->render($closing, $participant)
                : ($fullPdfData ??= $this->pdf->render($closing));
            $lines = [];
            foreach ($report['payment_instructions'] ?? $report['transfers'] as $transfer) {
                if ($transfer['from_id'] === $participant->id) {
                    $lines[] = __('mail.transfer_line', ['amount' => Money::format($transfer['amount_cents']), 'name' => $names[$transfer['to_id']]]);
                }
            }
            $body = str_replace(
                ['{name}', '{monat}', '{ueberweisungen}'],
                [$participant->name, $monthName, $lines === [] ? __('mail.no_transfers') : implode("\n", $lines)],
                Settings::mailBody(),
            );
            Mail::to($participant)->send(new MonthClosedMail($closing, $body, $pdfData));
        }
    }
}
