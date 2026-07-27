<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OperationalDatasets;
use App\Exports\XlsxWriter;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use App\Tenancy\Branding;
use App\Tenancy\TenantManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    use ParsesDateRange;

    /**
     * Dataset yang isinya adalah fitur berbayar tersendiri. Route hanya menjaga
     * `feature:export`, jadi tanpa peta ini tenant Basic bisa mengunduh data
     * BBM/GPS (yang dijual di paket Pro/Business) lewat URL langsung.
     */
    private const DATASET_FEATURE = [
        'fuel' => 'fuel_tracking',
        'mileage' => 'gps_tracking',
    ];

    public function __construct(private OperationalDatasets $datasets)
    {
    }

    public function download(Request $request, string $dataset, string $format): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $required = self::DATASET_FEATURE[$dataset] ?? null;
        if ($required && ! app(TenantManager::class)->current()?->hasFeature($required)) {
            abort(403, 'Fitur ini tidak tersedia pada paket langganan Anda.');
        }

        [$from, $to] = $this->range($request);

        $data = $this->datasets->get($dataset, $from, $to);
        abort_if($data === null, 404);

        $filename = $dataset.'_'.$from->format('Ymd').'-'.$to->format('Ymd').'.'.$format;

        if ($format === 'xlsx') {
            $path = (new XlsxWriter())->write($data['headings'], $data['rows'], $data['title']);

            return response()->download(
                $path,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend();
        }

        $pdf = Pdf::loadView('admin.exports.table-pdf', [
                'title' => $data['title'],
                'headings' => $data['headings'],
                'rows' => $data['rows'],
                'from' => $from,
                'to' => $to,
                'dated' => $data['dated'],
                'branding' => new Branding(app(TenantManager::class)->current()),
            ])
            ->setPaper('a4', count($data['headings']) > 6 ? 'landscape' : 'portrait');

        // Nomor halaman: CSS counter(pages) tak reliabel di dompdf (selalu 0).
        // API canvas native-nya yang benar-benar tahu total halaman setelah render.
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 130, $canvas->get_height() - 26,
            'Halaman {PAGE_NUM} dari {PAGE_COUNT}', null, 7.5, [0.6, 0.63, 0.67]
        );

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
