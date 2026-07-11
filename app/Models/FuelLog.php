<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'filled_at',
        'liters',
        'price_per_liter',
        'total_cost',
        'odometer_km',
        'full_tank',
        'station',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filled_at' => 'datetime',
            'liters' => 'float',
            'price_per_liter' => 'integer',
            'total_cost' => 'integer',
            'odometer_km' => 'integer',
            'full_tank' => 'boolean',
        ];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
