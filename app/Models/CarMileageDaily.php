<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarMileageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'car_mileage_daily';

    protected $fillable = ['tenant_id', 'car_id', 'date', 'km'];

    protected function casts(): array
    {
        return ['date' => 'date', 'km' => 'integer'];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
