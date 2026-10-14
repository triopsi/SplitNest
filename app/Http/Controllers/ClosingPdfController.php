<?php

namespace App\Http\Controllers;

use App\Models\MonthlyClosing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ClosingPdfController extends Controller
{
    public function __invoke(MonthlyClosing $closing): Response
    {
        abort_unless($closing->closed_at && $closing->snapshot, 404);

        return Pdf::loadView('pdf.closing', ['closing' => $closing, 'report' => $closing->snapshot])
            ->setPaper('a4')->setOption('isRemoteEnabled', false)
            ->download('SplitNest-Abrechnung-'.$closing->month.'.pdf');
    }
}
