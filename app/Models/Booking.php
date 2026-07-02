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
        'payment_status',
        'payment_ref',
        'paid_at',
        'notes',
    ];

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
