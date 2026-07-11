<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Parse rentang tanggal ?from / ?to yang dipakai halaman-halaman operasional
 * (laporan, BBM, export). Rentang terbalik otomatis ditukar.
 */
trait ParsesDateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request, ?Carbon $defaultFrom = null): array
    {
        $to = $this->parseDate($request->query('to')) ?? Carbon::today();
        $from = $this->parseDate($request->query('from'))
            ?? $defaultFrom
            ?? Carbon::today()->startOfYear();

        // Guard against an inverted range.
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->startOfDay()];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
