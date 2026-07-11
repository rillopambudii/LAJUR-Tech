<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #1c2430; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #56615e; margin-bottom: 14px; font-size: 8.5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #cfd6d3; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #0f1b33; color: #fff; font-size: 8.5px; }
        tr:nth-child(even) td { background: #f4f6f5; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .empty { text-align: center; color: #56615e; padding: 18px; }
    </style>
</head>
<body>
    <h1>{{ $title }} — Lajur</h1>
    <p class="meta">
        @if ($dated)
            Periode {{ $from->translatedFormat('d M Y') }} – {{ $to->translatedFormat('d M Y') }} ·
        @endif
        Dicetak {{ now()->translatedFormat('d M Y H:i') }}
    </p>

    <table>
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
</body>
</html>
