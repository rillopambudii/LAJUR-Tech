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
        ];
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
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
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
}
