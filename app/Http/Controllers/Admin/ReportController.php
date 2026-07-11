<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\ReportService;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Models\Booking;
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

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $filename = 'laporan-booking_'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Invoice', 'Tanggal', 'Mobil', 'Penyewa', 'Email', 'HP', 'Mulai', 'Selesai', 'Hari', 'Total', 'Status', 'Driver']);

            Booking::query()
                ->createdBetween($from->toDateString(), $to->toDateString())
                ->with('driver')
                ->orderBy('created_at')
                ->chunk(200, function ($bookings) use ($out) {
                    foreach ($bookings as $b) {
                        fputcsv($out, [
                            $b->invoiceNumber(),
                            optional($b->created_at)->format('Y-m-d H:i'),
                            $b->car_name,
                            $b->customer_name,
                            $b->customer_email,
                            $b->customer_phone,
                            $b->start_date->format('Y-m-d'),
                            $b->end_date->format('Y-m-d'),
                            $b->days,
                            $b->total_price,
                            $b->status_label,
                            $b->driver?->name ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

}
