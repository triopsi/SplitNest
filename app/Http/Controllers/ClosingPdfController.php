<?php

namespace App\Http\Controllers;

use App\Models\MonthlyClosing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Streams the PDF report for a closed month.
 */
class ClosingPdfController extends Controller
{
    /**
     * Download the frozen settlement snapshot as an A4 PDF.
     *
     * Open (not yet closed) months are treated as not found.
     */
    public function __invoke(MonthlyClosing $closing): Response
    {
        abort_unless($closing->closed_at && $closing->snapshot, 404);

        return Pdf::loadView('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])
            ->setPaper('a4')->setOption('isRemoteEnabled', false)
            ->download('SplitNest-Abrechnung-'.$closing->month.'.pdf');
    }
}
