<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Available roles within a tenant. */
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_DRIVER = 'driver';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLES = [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_DRIVER, self::ROLE_CUSTOMER, self::ROLE_SUPER_ADMIN];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'avatar_path',
        'password',
        'role',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Owners and admins can manage the tenant's back office. */
    public function isManager(): bool
    {
        return $this->hasRole(self::ROLE_OWNER, self::ROLE_ADMIN);
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** The dashboard route name this user lands on after login, keyed by role. */
    public function homeRouteName(): string
    {
        if ($this->hasRole(self::ROLE_DRIVER)) {
            return 'driver.dashboard';
        }

        if ($this->hasRole(self::ROLE_SUPER_ADMIN)) {
            return 'superadmin.plans.index';
        }

        return 'admin.dashboard';
    }

    /** URL foto profil, null bila belum diunggah. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Inisial 1-2 huruf untuk avatar placeholder saat belum ada foto. */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }

    /** Scope: users of a given role, ordered by name. */
    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role)->orderBy('name');
    }

    /** Scope: constrain to a tenant (User has no global tenant scope). */
    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Bookings this driver is assigned to.
     *
     * @return HasMany<Booking, $this>
     */
    public function driverBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'driver_id');
    }
}
