<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\ReportService;
use App\Exports\OperationalDatasets;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ParsesDateRange;

    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $summary = $this->reports->summary($from, $to);
        $revenueByMonth = $this->reports->revenueByMonth(12);
        $topCars = $this->reports->topCars($from, $to);

        $maxRevenue = max(1, (int) $revenueByMonth->max('value'));

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
            'revenueByMonth' => $revenueByMonth,
            'maxRevenue' => $maxRevenue,
            'topCars' => $topCars,
        ]);
    }

    public function export(Request $request, OperationalDatasets $datasets): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        // Kolom & query sama persis dengan export PDF/Excel (satu sumber
        // kebenaran di OperationalDatasets) — CSV tak boleh drift sendiri.
        $data = $datasets->get('bookings', $from, $to);

        $filename = 'laporan-booking_'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $data['headings']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

}
