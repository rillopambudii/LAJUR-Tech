<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteSettingRequest;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    public function edit(TenantManager $manager): View
    {
        return view('admin.site', ['tenant' => $manager->current()]);
    }

    public function update(SiteSettingRequest $request, TenantManager $manager): RedirectResponse
    {
        $tenant = $manager->current();
        $data = $request->validated();

        if ($request->boolean('remove_logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo'], $data['remove_logo']);

        $tenant->update($data);

        return redirect()->route('admin.site.edit')
            ->with('success', 'Pengaturan situs disimpan.');
    }
}
