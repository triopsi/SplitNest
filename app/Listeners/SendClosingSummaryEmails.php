<?php

namespace App\Listeners;

use App\Events\MonthClosed;
use App\Mail\MonthClosedMail;
use App\Models\Participant;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Sends every participant with an e-mail address the closing summary:
 * the frozen PDF statement plus their own balancing transfers.
 */
class SendClosingSummaryEmails
{
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
        $monthName = Carbon::createFromFormat('Y-m', $closing->month)->translatedFormat('F Y');
        $pdfData = Pdf::loadView('pdf.closing', ['closing' => $closing, 'report' => $report])
            ->setPaper('a4')->setOption('isRemoteEnabled', false)->output();

        foreach (Participant::query()->whereNotNull('email')->where('email', '!=', '')->get() as $participant) {
            $lines = [];
            foreach ($report['transfers'] as $transfer) {
                if ($transfer['from_id'] === $participant->id) {
                    $lines[] = '- '.Money::format($transfer['amount_cents']).' an '.$names[$transfer['to_id']];
                }
            }
            $body = str_replace(
                ['{name}', '{monat}', '{ueberweisungen}'],
                [$participant->name, $monthName, $lines === [] ? 'Keine — du bist ausgeglichen.' : implode("\n", $lines)],
                Settings::mailBody(),
            );
            Mail::to($participant)->send(new MonthClosedMail($closing, $body, $pdfData));
        }
    }
}
