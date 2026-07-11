<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OperationalDatasets;
use App\Exports\XlsxWriter;
use App\Http\Controllers\Concerns\ParsesDateRange;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    use ParsesDateRange;

    public function __construct(private OperationalDatasets $datasets)
    {
    }

    public function download(Request $request, string $dataset, string $format): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

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

        return Pdf::loadView('admin.exports.table-pdf', [
                'title' => $data['title'],
                'headings' => $data['headings'],
                'rows' => $data['rows'],
                'from' => $from,
                'to' => $to,
                'dated' => $data['dated'],
            ])
            ->setPaper('a4', count($data['headings']) > 6 ? 'landscape' : 'portrait')
            ->download($filename);
    }
}
