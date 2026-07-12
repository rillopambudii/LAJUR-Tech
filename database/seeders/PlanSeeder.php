<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Default plan config + which feature keys each plan includes. Editable later from /superadmin/plans. */
    private array $planDefaults = [
        'basic' => ['name' => 'Basic', 'price' => 150000, 'trial_days' => 14, 'sort_order' => 1, 'features' => []],
        'pro' => ['name' => 'Pro', 'price' => 350000, 'trial_days' => 14, 'sort_order' => 2, 'features' => [
            Feature::GPS_TRACKING, Feature::FUEL_TRACKING, Feature::EXPORT,
        ]],
        'business' => ['name' => 'Business', 'price' => 750000, 'trial_days' => 14, 'sort_order' => 3, 'features' => [
            Feature::GPS_TRACKING, Feature::FUEL_TRACKING, Feature::EXPORT, Feature::AI_ASSISTANT,
        ]],
    ];

    public function run(): void
    {
        foreach (Feature::NAMES as $key => $name) {
            Feature::updateOrCreate(['key' => $key], ['name' => $name]);
        }

        foreach ($this->planDefaults as $key => $data) {
            $plan = Plan::updateOrCreate(['key' => $key], [
                'name' => $data['name'],
                'price' => $data['price'],
                'trial_days' => $data['trial_days'],
                'sort_order' => $data['sort_order'],
            ]);

            $featureIds = Feature::whereIn('key', $data['features'])->pluck('id');
            $plan->features()->sync($featureIds);
        }
    }
}
