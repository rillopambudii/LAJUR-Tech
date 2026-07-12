<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'plan',
        'subscription_status',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public const PLANS = ['basic', 'pro', 'business'];

    public const STATUSES = ['trial', 'active', 'suspended', 'cancelled'];

    protected ?Plan $currentPlanCache = null;

    protected bool $currentPlanResolved = false;

    protected static function booted(): void
    {
        // Invalidate the per-instance plan cache whenever the `plan` attribute
        // changes, so a stale cached Plan is never served after e.g.
        // TrialGuard::settleIfExpired() downgrades the tenant mid-request.
        static::saved(function (Tenant $tenant) {
            if ($tenant->wasChanged('plan')) {
                $tenant->currentPlanResolved = false;
                $tenant->currentPlanCache = null;
            }
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Car, $this>
     */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    public function currentPlan(): ?Plan
    {
        if (! $this->currentPlanResolved) {
            $this->currentPlanCache = Plan::with('features')->where('key', $this->plan)->first();
            $this->currentPlanResolved = true;
        }

        return $this->currentPlanCache;
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->currentPlan()?->features->contains('key', $featureKey) ?? false;
    }
}
