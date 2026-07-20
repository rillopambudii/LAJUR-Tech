<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    protected $fillable = ['key', 'name', 'price', 'discount_price', 'discount_label', 'trial_days', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'discount_price' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** Diskon aktif hanya bila diisi dan benar-benar lebih murah dari harga normal. */
    public function hasDiscount(): bool
    {
        return $this->discount_price !== null && $this->discount_price < $this->price;
    }

    /** Harga yang benar-benar ditagihkan (dipakai checkout & semua tampilan harga). */
    public function effectivePrice(): int
    {
        return $this->hasDiscount() ? $this->discount_price : $this->price;
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'feature_plan');
    }
}
