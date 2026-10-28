<?php

namespace App\Services;

use App\Models\MonthlyClosing;
use App\Models\Participant;
use Barryvdh\DomPDF\Facade\Pdf;

/** Render the same recipient-specific PDF for mail delivery and administrator previews. */
final class ClosingReportPdf
{
    /** Build only the permitted statement for the participant, or the full administrator report. */
    public function render(MonthlyClosing $closing, ?Participant $participant = null): string
    {
        if ($participant?->only_own_report) {
            return Pdf::loadView('pdf.participant-closing', [
                'statement' => app(ParticipantStatement::class)->forParticipant($closing, $participant),
            ])->setPaper('a4')->setOption('isRemoteEnabled', false)->output();
        }

        return Pdf::loadView('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])
            ->setPaper('a4')->setOption('isRemoteEnabled', false)->output();
    }
}
