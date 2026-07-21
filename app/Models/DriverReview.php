<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverReview extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'driver_id',
        'rating_punctuality',
        'rating_cleanliness',
        'rating_friendliness',
        'rating_safety',
        'rating_overall',
        'comment',
        'status',
        'admin_reply',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating_punctuality' => 'integer',
            'rating_cleanliness' => 'integer',
            'rating_friendliness' => 'integer',
            'rating_safety' => 'integer',
            'rating_overall' => 'float',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Nama customer disamarkan untuk tampil di profil publik driver. */
    public function maskedCustomerName(): string
    {
        $name = trim((string) $this->booking?->customer_name);
        $words = preg_split('/\s+/', $name);

        if (count($words) < 2) {
            return $name;
        }

        $last = array_pop($words);

        return implode(' ', $words).' '.mb_strtoupper(mb_substr($last, 0, 1)).'.';
    }
}
