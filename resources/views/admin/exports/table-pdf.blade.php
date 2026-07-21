<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $branding->name() }}</title>
    <style>
        @php($accent = $branding->accentColor() ?: '#0F1B33')
        @php($accentDark = $branding->accentDark() ?: '#0A1220')
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9.5px; color: #1c2430; margin: 0; }
        .page { padding: 28px 30px 60px; }

        /* ---------- Kop surat ---------- */
        .accent-bar { height: 6px; background: {{ $accent }}; }
        .header-table { width: 100%; border-collapse: collapse; margin: 20px 0 4px; }
        .header-table td { border: 0; padding: 0; vertical-align: top; }
        .brand-logo { max-height: 42px; max-width: 140px; margin-bottom: 6px; }
        .brand-name { font-size: 17px; font-weight: 700; color: #1c2430; margin: 0 0 2px; }
        .brand-meta { font-size: 8px; color: #6b7480; line-height: 1.5; }
        .report-label { font-size: 8px; letter-spacing: 1.5px; color: {{ $accentDark }}; font-weight: 700; text-align: right; margin: 0 0 4px; }
        .report-title { font-size: 15px; font-weight: 700; color: #1c2430; text-align: right; margin: 0 0 4px; }
        .report-period { font-size: 8.5px; color: #6b7480; text-align: right; }

        .divider { border-top: 1px solid #dde2e6; margin: 14px 0 18px; }

        /* ---------- Tabel data ---------- */
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: {{ $accentDark }}; color: #fff; font-size: 8.5px; font-weight: 700;
            text-align: left; padding: 7px 8px; letter-spacing: .3px;
        }
        table.data td { padding: 6px 8px; border-bottom: 0.75px solid #e7eaed; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f7f8fa; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .empty { text-align: center; color: #6b7480; padding: 24px; }

        /* ---------- Footer tetap di tiap halaman ---------- */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0; height: 40px;
            padding: 10px 30px 0; border-top: 0.75px solid #dde2e6;
            font-size: 7.5px; color: #9aa2ab;
        }
        .footer .l { float: left; }
        {{-- Nomor halaman digambar via Dompdf\Canvas::page_text() di ExportController
             (CSS counter(pages) tak reliabel di dompdf — selalu menunjukkan 0). --}}
    </style>
</head>
<body>
    <div class="accent-bar"></div>
    <div class="page">
        <table class="header-table">
            <tr>
                <td style="width:55%">
                    @if ($branding->logoUrl())
                        <img class="brand-logo" src="{{ $branding->logoUrl() }}" alt="{{ $branding->name() }}">
                    @else
                        <div class="brand-name">{{ $branding->name() }}</div>
                    @endif
                    <div class="brand-meta">
                        {{ $branding->address() }}<br>
                        {{ $branding->phone() }} · {{ $branding->email() }}
                    </div>
                </td>
                <td style="width:45%">
                    <div class="report-label">LAPORAN</div>
                    <div class="report-title">{{ $title }}</div>
                    <div class="report-period">
                        @if ($dated)
                            Periode {{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }}<br>
                        @endif
                        Dicetak {{ now()->translatedFormat('d M Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="data">
            <thead>
                <tr>
                    @foreach ($headings as $h)
                        <th>{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="{{ is_int($cell) || is_float($cell) ? 'num' : '' }}">
                                {{ is_int($cell) ? number_format($cell, 0, ',', '.') : (is_float($cell) ? number_format($cell, 1, ',', '.') : $cell) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headings) }}" class="empty">Tidak ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <span class="l">{{ $branding->name() }} · dibuat otomatis via Lajur</span>
    </div>
</body>
</html>
