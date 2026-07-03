<?php

namespace App\AI;

use App\Analytics\ReportService;
use App\Models\Car;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the dashboard "AI summary" card. Metrics are computed deterministically
 * (tenant-scoped, via ReportService); the AI only narrates them into a short
 * briefing. Result is cached per tenant; a deterministic fallback is used when
 * the AI is disabled or errors, so the card is always useful.
 */
class DashboardInsightService
{
    private const CACHE_HOURS = 3;

    public function __construct(
        private ReportService $reports,
        private TenantManager $tenants,
        private AssistantService $assistant,
    ) {
    }

    /**
     * @return array{text: string, source: string}
     */
    public function get(bool $fresh = false): array
    {
        $key = 'ai_insight:'.($this->tenants->id() ?? 'none');
        if ($fresh) {
            Cache::forget($key);
        }

        $metrics = $this->metrics();

        if ($this->assistant->isConfigured()) {
            try {
                $text = Cache::remember(
                    $key,
                    now()->addHours(self::CACHE_HOURS),
                    fn () => $this->assistant->narrate($this->systemPrompt(), $this->dataPrompt($metrics)),
                );

                return ['text' => $text, 'source' => 'ai'];
            } catch (\Throwable) {
                // fall through to the deterministic summary
            }
        }

        return ['text' => $this->fallback($metrics), 'source' => 'fallback'];
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $today = Carbon::today();
        $cur = $this->reports->summary($today->copy()->startOfMonth(), $today);
        $prevRevenue = (int) $this->reports->summary(
            $today->copy()->subMonthNoOverflow()->startOfMonth(),
            $today->copy()->subMonthNoOverflow()->endOfMonth(),
        )['revenue'];

        $top = $this->reports->topCars($today->copy()->startOfMonth(), $today, 1)->first();

        return [
            'pendapatan_bulan_ini' => (int) $cur['revenue'],
            'pendapatan_bulan_lalu' => $prevRevenue,
            'total_booking_bulan_ini' => (int) $cur['bookings_total'],
            'booking_pending' => (int) ($cur['status_breakdown']['pending'] ?? 0),
            'okupansi_armada_persen' => $cur['utilization'],
            'mobil_terlaris_bulan_ini' => $top->car_name ?? null,
            'mobil_pengingat_pajak_servis' => (int) Car::query()->withDueReminders()->count(),
        ];
    }

    private function systemPrompt(): string
    {
        $business = $this->tenants->current()?->name ?? config('app.name');

        return "Anda asisten bisnis untuk rental mobil \"{$business}\". Dari data JSON di bawah, "
            .'tulis "briefing harian" SANGAT singkat untuk pemilik: 2-3 kalimat/poin paling penting. '
            .'Bahasa Indonesia yang ramah, format rupiah (mis. Rp 12.500.000). Soroti perubahan penting '
            .'dan hal yang butuh tindakan (booking pending, pajak/servis jatuh tempo). '
            .'Jangan mengarang angka di luar data. Maksimal sekitar 45 kata.';
    }

    /** @param array<string, mixed> $metrics */
    private function dataPrompt(array $metrics): string
    {
        return json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** @param array<string, mixed> $metrics */
    private function fallback(array $metrics): string
    {
        $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
        $cur = (int) $metrics['pendapatan_bulan_ini'];
        $prev = (int) $metrics['pendapatan_bulan_lalu'];

        $line = 'Pendapatan bulan ini '.$rp($cur);
        if ($prev > 0) {
            $pct = round(($cur - $prev) / $prev * 100);
            $line .= ' ('.($pct >= 0 ? '+' : '').$pct.'% vs bulan lalu)';
        }
        $parts = [$line.'.'];

        if (($metrics['booking_pending'] ?? 0) > 0) {
            $parts[] = $metrics['booking_pending'].' booking pending perlu konfirmasi.';
        }
        if (($metrics['mobil_pengingat_pajak_servis'] ?? 0) > 0) {
            $parts[] = $metrics['mobil_pengingat_pajak_servis'].' mobil mendekati jatuh tempo pajak/servis.';
        }

        return implode(' ', $parts);
    }
}
