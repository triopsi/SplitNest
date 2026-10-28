<?php

namespace App\Http\Controllers;

use App\Models\MonthlyClosing;
use App\Models\Participant;
use App\Services\ClosingReportPdf;
use Illuminate\Http\Response;

/** Let administrators inspect exactly the PDF that a participant would receive. */
class ParticipantClosingPdfController extends Controller
{
    /** Download the recipient's permitted report from a closed snapshot only. */
    public function __invoke(MonthlyClosing $closing, Participant $participant, ClosingReportPdf $pdf): Response
    {
        abort_unless($closing->closed_at && $closing->snapshot, 404);

        return response($pdf->render($closing, $participant), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="SplitNest-'.$closing->month.'-'.$participant->id.'.pdf"',
        ]);
    }
}
