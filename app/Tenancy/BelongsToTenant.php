<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;

/**
 * Applied to every tenant-owned model. Adds:
 *   - a global scope that filters queries to the current tenant, and
 *   - automatic population of tenant_id on create.
 *
 * Safety: when there is no tenant context (console, seeding, super-admin) the
 * scope is a no-op, so the model behaves exactly as it did before tenancy.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $manager = app(TenantManager::class);

            if (empty($model->tenant_id) && $manager->shouldScope()) {
                $model->tenant_id = $manager->id();
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

/**
 * Global scope that constrains every query to the active tenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(TenantManager::class);

        if ($manager->shouldScope()) {
            $builder->where($model->getTable().'.tenant_id', $manager->id());
        }
    }
}
