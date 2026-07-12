<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    public const GPS_TRACKING = 'gps_tracking';
    public const FUEL_TRACKING = 'fuel_tracking';
    public const EXPORT = 'export';
    public const AI_ASSISTANT = 'ai_assistant';

    /** key => display name, used by PlanSeeder to create rows. */
    public const NAMES = [
        self::GPS_TRACKING => 'Pelacakan GPS',
        self::FUEL_TRACKING => 'BBM & Solar (anti-kebocoran)',
        self::EXPORT => 'Export PDF/Excel',
        self::AI_ASSISTANT => 'Asisten AI',
    ];

    protected $fillable = ['key', 'name', 'description'];

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'feature_plan');
    }
}
