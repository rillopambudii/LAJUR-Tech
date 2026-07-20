<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Tenancy\BelongsToTenant;

class Booking extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'car_id',
        'driver_id',
        'destination',
        'car_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'start_date',
        'end_date',
        'days',
        'price_per_day',
        'total_price',
        'status',
        'trip_status',
        'eta_manual_note',
        'booking_code',
        'payment_status',
        'payment_ref',
        'paid_at',
        'notes',
    ];

    /** Link Google Maps universal: buka app Maps dgn rute ke tujuan (titik awal = posisi pengguna). */
    public function mapsUrl(): ?string
    {
        return $this->destination
            ? 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($this->destination)
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'price_per_day' => 'integer',
            'total_price' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Belum Bayar',
        'pending' => 'Menunggu Pembayaran',
        'paid' => 'Lunas',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
    ];

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    public const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'confirmed' => 'Dikonfirmasi',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    // Physical delivery stage of the car (independent of the transaction `status`).
    public const TRIP_NOT_STARTED = 'not_started';
    public const TRIP_PREPARING   = 'preparing';
    public const TRIP_ON_THE_WAY  = 'on_the_way';
    public const TRIP_ARRIVED     = 'arrived';
    public const TRIP_COMPLETED   = 'completed';

    /** @var list<string> */
    public const TRIP_STATUSES = [
        self::TRIP_NOT_STARTED,
        self::TRIP_PREPARING,
        self::TRIP_ON_THE_WAY,
        self::TRIP_ARRIVED,
        self::TRIP_COMPLETED,
    ];

    public const TRIP_STATUS_LABELS = [
        self::TRIP_NOT_STARTED => 'Belum Diproses',
        self::TRIP_PREPARING   => 'Sedang Disiapkan',
        self::TRIP_ON_THE_WAY  => 'Dalam Perjalanan',
        self::TRIP_ARRIVED     => 'Sudah Tiba',
        self::TRIP_COMPLETED   => 'Selesai',
    ];

    /**
     * Statuses that reserve a car for its date range (i.e. block other bookings).
     * Cancelled never blocks; completed is historical and does not block future dates.
     */
    public const BLOCKING_STATUSES = ['pending', 'confirmed'];

    /**
     * Statuses that count as realised/committed revenue. Single source of truth
     * for every revenue figure (analytics, reports, and the future AI queries).
     */
    public const REVENUE_STATUSES = ['confirmed', 'completed'];

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** Scope: filter by status. */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $q) => $q->where('status', $status));
    }

    /** Scope: only bookings that currently reserve a car (block availability). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKING_STATUSES);
    }

    /** Scope: bookings that count towards revenue (confirmed + completed). */
    public function scopeRevenue(Builder $query): Builder
    {
        return $query->whereIn('status', self::REVENUE_STATUSES);
    }

    /** Scope: bookings created within an inclusive date range. */
    public function scopeCreatedBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);
    }

    /**
     * Scope: bookings whose date range overlaps [$start, $end] (inclusive).
     * Two inclusive ranges overlap when start_a <= end_b AND end_a >= start_b.
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }

    /** Human-readable status label. */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Human-readable delivery (trip) status label. */
    public function getTripStatusLabelAttribute(): string
    {
        return self::TRIP_STATUS_LABELS[$this->trip_status] ?? self::TRIP_STATUS_LABELS[self::TRIP_NOT_STARTED];
    }

    /** Progress 0-100 for the tracking page progress bar. */
    public function getTripProgressAttribute(): int
    {
        return match ($this->trip_status) {
            self::TRIP_NOT_STARTED => 10,
            self::TRIP_PREPARING   => 35,
            self::TRIP_ON_THE_WAY  => 70,
            self::TRIP_ARRIVED, self::TRIP_COMPLETED => 100,
            default                => 10,
        };
    }

    /**
     * True when this booking's car has a fresh GPS position (< 5 min old).
     *
     * The tracking page uses this to decide between the live map (Phase 2) and the
     * text fallback. GPS lives per-car in `vehicle_positions` (Traccar), so we read
     * the car's latest ping rather than duplicating coordinates onto the booking.
     */
    public function getHasLiveGpsAttribute(): bool
    {
        $position = $this->car?->latestPosition;

        if ($position === null || $position->device_time === null) {
            return false;
        }

        return $position->device_time->diffInMinutes(now()) < 5;
    }

    /**
     * Generate a unique public booking code, format LJR-XXXXXX (6 chars, no
     * ambiguous 0/O/1/I). Called when a booking is created. The uniqueness check
     * ignores the tenant scope because the `booking_code` unique index is global.
     */
    public static function generateBookingCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'LJR-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (self::withoutGlobalScope(\App\Tenancy\TenantScope::class)->where('booking_code', $code)->exists());

        return $code;
    }

    /** Total km driven during this booking's rental window (from car_mileage_daily). */
    public function distanceKm(): int
    {
        if ($this->car_id === null) {
            return 0;
        }

        return (int) CarMileageDaily::query()
            ->where('car_id', $this->car_id)
            ->whereBetween('date', [$this->start_date->toDateString(), $this->end_date->toDateString()])
            ->sum('km');
    }

    /** Invoice number, e.g. INV/LAJUR/2026/0007. */
    public function invoiceNumber(): string
    {
        $slug = strtoupper($this->tenant?->slug ?? 'INV');
        $year = ($this->created_at ?? now())->format('Y');

        return sprintf('INV/%s/%s/%04d', $slug, $year, $this->id);
    }

    /**
     * Normalise the customer's phone to an international number for wa.me
     * (Indonesian default: a leading 0 becomes 62).
     */
    public function whatsappNumber(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->customer_phone);

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    /** Build a click-to-chat WhatsApp URL with an optional prefilled message. */
    public function whatsappUrl(?string $message = null): string
    {
        $url = 'https://wa.me/'.$this->whatsappNumber();

        return $message ? $url.'?text='.rawurlencode($message) : $url;
    }
}
