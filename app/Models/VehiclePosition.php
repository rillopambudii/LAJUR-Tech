<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single GPS ping for a car. Populated by the Traccar sync (integration phase);
 * tenant-scoped like every other business model.
 */
class VehiclePosition extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'car_id',
        'latitude',
        'longitude',
        'speed',
        'course',
        'device_time',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed' => 'integer',
            'course' => 'integer',
            'device_time' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
