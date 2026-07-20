<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Content\LandingCopy;
use App\Http\Controllers\Controller;
use App\Models\LandingContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingContentController extends Controller
{
    public function edit(): View
    {
        $stored = LandingContent::current();
        $copy = new LandingCopy($stored);

        return view('superadmin.landing.edit', compact('stored', 'copy'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hero_eyebrow' => ['nullable', 'string', 'max:150'],
            'hero_title_lead' => ['nullable', 'string', 'max:150'],
            'hero_title_reveal' => ['nullable', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:400'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'spotlight_eyebrow' => ['nullable', 'string', 'max:60'],
            'trust_lead' => ['nullable', 'string', 'max:200'],
            'trust_items' => ['nullable', 'array'],
            'trust_items.*' => ['nullable', 'string', 'max:80'],
            'pain_eyebrow' => ['nullable', 'string', 'max:60'],
            'pain_title' => ['nullable', 'string', 'max:150'],
            'pain_subtitle' => ['nullable', 'string', 'max:300'],
            'pain_items' => ['nullable', 'array'],
            'pain_items.*.title' => ['nullable', 'string', 'max:120'],
            'pain_items.*.text' => ['nullable', 'string', 'max:300'],
            'pain_closing' => ['nullable', 'string', 'max:150'],
            'before_items' => ['nullable', 'array'],
            'before_items.*' => ['nullable', 'string', 'max:80'],
            'after_brand' => ['nullable', 'string', 'max:80'],
            'after_text' => ['nullable', 'string', 'max:300'],
            'features_title' => ['nullable', 'string', 'max:150'],
            'features_subtitle' => ['nullable', 'string', 'max:200'],
            'feature_groups' => ['nullable', 'array'],
            'feature_groups.*.title' => ['nullable', 'string', 'max:80'],
            'feature_groups.*.items' => ['nullable', 'array'],
            'feature_groups.*.items.*' => ['nullable', 'string', 'max:150'],
            'spotlight_fuel_title' => ['nullable', 'string', 'max:150'],
            'spotlight_fuel_text' => ['nullable', 'string', 'max:600'],
            'spotlight_driver_title' => ['nullable', 'string', 'max:150'],
            'spotlight_driver_text' => ['nullable', 'string', 'max:600'],
            'family_title' => ['nullable', 'string', 'max:150'],
            'family_subtitle' => ['nullable', 'string', 'max:300'],
            'family_steps' => ['nullable', 'array'],
            'family_steps.*.title' => ['nullable', 'string', 'max:80'],
            'family_steps.*.text' => ['nullable', 'string', 'max:200'],
            'spotlight_storefront_title' => ['nullable', 'string', 'max:150'],
            'spotlight_storefront_text' => ['nullable', 'string', 'max:600'],
            'gps_badge' => ['nullable', 'string', 'max:40'],
            'gps_title' => ['nullable', 'string', 'max:150'],
            'gps_text' => ['nullable', 'string', 'max:400'],
            'gps_note' => ['nullable', 'string', 'max:200'],
            'why_title' => ['nullable', 'string', 'max:150'],
            'why_items' => ['nullable', 'array'],
            'why_items.*.title' => ['nullable', 'string', 'max:80'],
            'why_items.*.text' => ['nullable', 'string', 'max:200'],
            'workflow_title' => ['nullable', 'string', 'max:150'],
            'workflow_steps' => ['nullable', 'array'],
            'workflow_steps.*.title' => ['nullable', 'string', 'max:80'],
            'workflow_steps.*.text' => ['nullable', 'string', 'max:200'],
            'ecosystem_title_line1' => ['nullable', 'string', 'max:150'],
            'ecosystem_title_line2' => ['nullable', 'string', 'max:150'],
            'ecosystem_subtitle' => ['nullable', 'string', 'max:300'],
            'ecosystem_items' => ['nullable', 'array'],
            'ecosystem_items.*' => ['nullable', 'string', 'max:80'],
            'ecosystem_caption' => ['nullable', 'string', 'max:200'],
            'pricing_title' => ['nullable', 'string', 'max:150'],
            'pricing_subtitle' => ['nullable', 'string', 'max:300'],
            'cta_title' => ['nullable', 'string', 'max:150'],
            'cta_text' => ['nullable', 'string', 'max:300'],
            'cta_trust_items' => ['nullable', 'array'],
            'cta_trust_items.*' => ['nullable', 'string', 'max:100'],
        ]);

        LandingContent::query()->updateOrCreate(['id' => 1], ['content' => $data]);

        return redirect()->route('superadmin.landing.edit')
            ->with('success', 'Konten landing page disimpan.');
    }
}
