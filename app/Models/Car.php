<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Tenancy\BelongsToTenant;

class Car extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'plate_number',
        'traccar_device_id',
        'brand',
        'type',
        'transmission',
        'fuel_type',
        'seats',
        'price_per_day',
        'image',
        'description',
        'tax_due_date',
        'service_due_date',
        'is_available',
        'is_featured',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'price_per_day' => 'integer',
            'sort_order' => 'integer',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'tax_due_date' => 'date',
            'service_due_date' => 'date',
        ];
    }

    /** Days before a due date within which we flag it as "due soon". */
    public const REMINDER_WINDOW_DAYS = 30;

    /** Allowed car types. */
    public const TYPES = ['SUV', 'MPV', 'Sedan', 'Hatchback', 'Luxury', 'Pickup'];

    /** Allowed transmissions. */
    public const TRANSMISSIONS = ['Automatic', 'Manual'];

    /** Allowed fuel types. */
    public const FUEL_TYPES = ['Bensin', 'Diesel', 'Listrik', 'Hybrid'];

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<VehiclePosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(VehiclePosition::class);
    }

    /**
     * @return HasOne<VehiclePosition, $this>
     */
    public function latestPosition(): HasOne
    {
        return $this->hasOne(VehiclePosition::class)->latestOfMany('device_time');
    }

    /** Scope: only available cars. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }

    /**
     * Whether this car can be booked for the given inclusive date range.
     * A car is bookable when it is marked available AND has no active
     * (pending/confirmed) booking overlapping the range. Pass $ignoreBookingId
     * to exclude a booking being edited from the conflict check.
     */
    public function isAvailableForRange(string $start, string $end, ?int $ignoreBookingId = null): bool
    {
        if (! $this->is_available) {
            return false;
        }

        return ! $this->bookings()
            ->active()
            ->overlapping($start, $end)
            ->when($ignoreBookingId, fn (Builder $q) => $q->where('id', '!=', $ignoreBookingId))
            ->exists();
    }

    /** Scope: only featured cars. */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: cars with a tax or service date already due or due within the
     * reminder window, nearest date first.
     */
    public function scopeWithDueReminders(Builder $query): Builder
    {
        $limit = now()->addDays(self::REMINDER_WINDOW_DAYS)->toDateString();

        return $query->where(function (Builder $q) use ($limit) {
            $q->whereNotNull('tax_due_date')->where('tax_due_date', '<=', $limit)
                ->orWhere(function (Builder $w) use ($limit) {
                    $w->whereNotNull('service_due_date')->where('service_due_date', '<=', $limit);
                });
        });
    }

    /**
     * Reminder state for a due date: 'overdue', 'soon' (within the window),
     * 'ok', or null when no date is set.
     */
    private function reminderState(?\Illuminate\Support\Carbon $due): ?string
    {
        if ($due === null) {
            return null;
        }

        $today = now()->startOfDay();

        if ($due->lt($today)) {
            return 'overdue';
        }

        return $due->lte($today->copy()->addDays(self::REMINDER_WINDOW_DAYS)) ? 'soon' : 'ok';
    }

    public function taxStatus(): ?string
    {
        return $this->reminderState($this->tax_due_date);
    }

    public function serviceStatus(): ?string
    {
        return $this->reminderState($this->service_due_date);
    }

    /** Whether any reminder is overdue or due soon. */
    public function hasDueReminder(): bool
    {
        return in_array($this->taxStatus(), ['overdue', 'soon'], true)
            || in_array($this->serviceStatus(), ['overdue', 'soon'], true);
    }

    /** Scope: apply the default display ordering. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Resolve the image to a usable URL, supporting both external URLs
     * and locally-stored files. Returns null when no image is set.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    /** Whether the stored image is a local (storage) file. */
    public function hasLocalImage(): bool
    {
        return filled($this->image)
            && ! Str::startsWith($this->image, ['http://', 'https://']);
    }
}
