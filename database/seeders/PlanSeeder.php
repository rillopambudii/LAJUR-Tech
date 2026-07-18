<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /** Default plan config + which feature keys each plan includes. Editable later from /superadmin/plans. */
    private array $planDefaults = [
        // Struktur decoy: Pro cuma menambah AI di atas Basic; Business menambah
        // BBM + GPS sekaligus → lompatan Pro→Business terasa jauh lebih worth it.
        // Harga decoy: lompatan Pro→Business sengaja kecil (Rp 200rb) padahal
        // Business menambah BBM + GPS → dorong "sekalian Business".
        'basic' => ['name' => 'Basic', 'price' => 799000, 'trial_days' => 14, 'sort_order' => 1, 'features' => [
            Feature::EXPORT,
        ]],
        'pro' => ['name' => 'Pro', 'price' => 1299000, 'trial_days' => 14, 'sort_order' => 2, 'features' => [
            Feature::EXPORT, Feature::AI_ASSISTANT,
        ]],
        'business' => ['name' => 'Business', 'price' => 1499000, 'trial_days' => 14, 'sort_order' => 3, 'features' => [
            Feature::EXPORT, Feature::AI_ASSISTANT, Feature::FUEL_TRACKING, Feature::GPS_TRACKING,
        ]],
    ];

    public function run(): void
    {
        foreach (Feature::NAMES as $key => $name) {
            Feature::updateOrCreate(['key' => $key], ['name' => $name]);
        }

        foreach ($this->planDefaults as $key => $data) {
            // firstOrCreate: only apply defaults on first boot. Price/trial_days
            // and feature assignments become owner-editable via /superadmin/plans
            // afterwards, so re-seeding must never clobber those manual edits.
            $plan = Plan::firstOrCreate(['key' => $key], [
                'name' => $data['name'],
                'price' => $data['price'],
                'trial_days' => $data['trial_days'],
                'sort_order' => $data['sort_order'],
            ]);

            if ($plan->wasRecentlyCreated) {
                $featureIds = Feature::whereIn('key', $data['features'])->pluck('id');
                $plan->features()->sync($featureIds);
            }
        }
    }
}
