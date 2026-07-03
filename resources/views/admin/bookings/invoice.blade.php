<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $booking->invoiceNumber() }}</title>
    <style>
        :root{--ink:#1a1c1a;--muted:#6b7280;--line:#e5e7eb;--petrol:#0f5a5e}
        *{box-sizing:border-box}
        body{font-family:'Segoe UI',system-ui,Arial,sans-serif;color:var(--ink);margin:0;background:#f3f4f6;padding:28px}
        .sheet{max-width:800px;margin:0 auto;background:#fff;padding:44px 48px;border-radius:10px;box-shadow:0 6px 30px rgba(0,0,0,.08)}
        .top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid var(--ink);padding-bottom:20px}
        .biz{font-size:1.5rem;font-weight:800;letter-spacing:.02em}
        .biz small{display:block;font-size:.8rem;font-weight:500;color:var(--muted);letter-spacing:0;margin-top:2px}
        .inv-meta{text-align:right;font-size:.85rem}
        .inv-meta .num{font-size:1.1rem;font-weight:700;color:var(--petrol)}
        .parties{display:flex;justify-content:space-between;gap:30px;margin:26px 0}
        .parties h3{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin:0 0 6px}
        .parties .v{font-size:.92rem;line-height:1.5}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th,td{text-align:left;padding:11px 12px;font-size:.9rem}
        thead th{background:#f8fafc;border-bottom:1px solid var(--line);font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
        tbody td{border-bottom:1px solid var(--line)}
        .num-col{text-align:right;font-variant-numeric:tabular-nums}
        .totals{margin-top:18px;margin-left:auto;width:280px;font-size:.92rem}
        .totals .row{display:flex;justify-content:space-between;padding:6px 0}
        .totals .grand{border-top:2px solid var(--ink);margin-top:6px;padding-top:12px;font-size:1.15rem;font-weight:800;color:var(--petrol)}
        .status{display:inline-block;padding:4px 12px;border-radius:999px;font-size:.78rem;font-weight:700;border:1px solid var(--line)}
        .foot{margin-top:34px;padding-top:18px;border-top:1px solid var(--line);font-size:.8rem;color:var(--muted);line-height:1.6}
        .actions{max-width:800px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end}
        .btn{border:0;background:var(--petrol);color:#fff;padding:10px 18px;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none}
        .btn.ghost{background:#fff;color:var(--ink);border:1px solid var(--line)}
        @media print{
            body{background:#fff;padding:0}
            .sheet{box-shadow:none;border-radius:0;max-width:none;padding:0}
            .actions{display:none}
            @page{margin:18mm}
        }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('admin.bookings.show', $booking) }}" class="btn ghost">&larr; Kembali</a>
        <button class="btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <div class="top">
            <div class="biz">
                {{ $booking->tenant?->name ?? config('app.name') }}
                <small>Rental Mobil &amp; Travel</small>
            </div>
            <div class="inv-meta">
                <div class="num">{{ $booking->invoiceNumber() }}</div>
                <div>Tanggal: {{ ($booking->created_at ?? now())->translatedFormat('d F Y') }}</div>
                <div style="margin-top:6px"><span class="status">{{ $booking->status_label }}</span></div>
            </div>
        </div>

        <div class="parties">
            <div>
                <h3>Ditagihkan kepada</h3>
                <div class="v">
                    <strong>{{ $booking->customer_name }}</strong><br>
                    {{ $booking->customer_email }}<br>
                    {{ $booking->customer_phone }}
                </div>
            </div>
            <div style="text-align:right">
                <h3>Periode Sewa</h3>
                <div class="v">
                    {{ $booking->start_date->translatedFormat('d M Y') }} &ndash;
                    {{ $booking->end_date->translatedFormat('d M Y') }}<br>
                    {{ $booking->days }} hari
                    @if ($booking->driver)<br>Driver: {{ $booking->driver->name }}@endif
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Deskripsi</th><th class="num-col">Harga/Hari</th><th class="num-col">Hari</th><th class="num-col">Jumlah</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Sewa {{ $booking->car_name }}</td>
                    <td class="num-col">Rp {{ number_format($booking->price_per_day, 0, ',', '.') }}</td>
                    <td class="num-col">{{ $booking->days }}</td>
                    <td class="num-col">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span></div>
            <div class="row grand"><span>Total</span><span>Rp {{ number_format($booking->total_price, 0, ',', '.') }}</span></div>
        </div>

        <div class="foot">
            Terima kasih telah mempercayakan perjalanan Anda kepada {{ $booking->tenant?->name ?? config('app.name') }}.<br>
            Invoice ini dibuat otomatis dan sah tanpa tanda tangan.
        </div>
    </div>
</body>
</html>
