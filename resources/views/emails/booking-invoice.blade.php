<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f4f6;font-family:'Segoe UI',system-ui,Arial,sans-serif;color:#1a1c1a">
    <div style="max-width:560px;margin:0 auto;padding:24px">
        <div style="background:#0f5a5e;color:#fff;padding:22px 26px;border-radius:12px 12px 0 0">
            <div style="font-size:1.25rem;font-weight:800">{{ $business }}</div>
            <div style="opacity:.85;font-size:.85rem">Invoice {{ $booking->invoiceNumber() }}</div>
        </div>
        <div style="background:#fff;padding:26px;border-radius:0 0 12px 12px">
            <p style="margin:0 0 14px">Halo <strong>{{ $booking->customer_name }}</strong>,</p>
            <p style="margin:0 0 18px;line-height:1.6">
                Terima kasih atas pemesanan Anda. Berikut rincian sewa kendaraan Anda:
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:.92rem">
                <tr><td style="padding:6px 0;color:#6b7280">Mobil</td><td style="padding:6px 0;text-align:right;font-weight:600">{{ $booking->car_name }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7280">Periode</td><td style="padding:6px 0;text-align:right">{{ $booking->start_date->translatedFormat('d M Y') }} – {{ $booking->end_date->translatedFormat('d M Y') }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7280">Lama</td><td style="padding:6px 0;text-align:right">{{ $booking->days }} hari</td></tr>
                <tr><td style="padding:6px 0;color:#6b7280">Status</td><td style="padding:6px 0;text-align:right">{{ $booking->status_label }}</td></tr>
                <tr><td style="padding:12px 0 0;border-top:2px solid #1a1c1a;font-weight:800">Total</td>
                    <td style="padding:12px 0 0;border-top:2px solid #1a1c1a;text-align:right;font-weight:800;color:#0f5a5e;font-size:1.15rem">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td></tr>
            </table>

            <p style="margin:22px 0 0;line-height:1.6;color:#6b7280;font-size:.85rem">
                Tim kami akan menghubungi Anda untuk konfirmasi pembayaran dan penjemputan.
                Jika ada pertanyaan, balas email ini.
            </p>
        </div>
        <p style="text-align:center;color:#9ca3af;font-size:.75rem;margin:16px 0 0">
            {{ $business }} · Invoice dibuat otomatis
        </p>
    </div>
</body>
</html>
