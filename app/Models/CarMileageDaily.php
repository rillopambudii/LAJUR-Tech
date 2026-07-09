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

    // `date` stays a plain 'Y-m-d' string (no date cast): a date cast persists as
    // a full datetime on sqlite, which breaks updateOrCreate's (car_id, date)
    // lookup against the stored value. String comparison is correct on both
    // sqlite and MySQL date columns.
    protected function casts(): array
    {
        return ['km' => 'integer'];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
