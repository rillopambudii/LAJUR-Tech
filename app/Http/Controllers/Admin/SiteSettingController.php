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

        // Logo
        if ($request->boolean('remove_logo')) {
            $this->deleteFile($tenant->logo_path);
            $data['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            $this->deleteFile($tenant->logo_path);
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        // Foto hero (pola sama dengan logo)
        if ($request->boolean('remove_hero_image')) {
            $this->deleteFile($tenant->hero_image_path);
            $data['hero_image_path'] = null;
        } elseif ($request->hasFile('hero_image')) {
            $this->deleteFile($tenant->hero_image_path);
            $data['hero_image_path'] = $request->file('hero_image')->store('hero', 'public');
        }

        // Keunggulan: buang baris yang judulnya kosong sebelum disimpan.
        if (array_key_exists('why_us', $data)) {
            $data['why_us'] = array_values(array_filter(
                $data['why_us'] ?? [],
                fn ($row) => trim((string) ($row['title'] ?? '')) !== ''
            )) ?: null;
        }

        // Checkbox: HTML tak mengirim yang tak dicentang → set eksplisit dari request.
        foreach (['splash_enabled', 'show_about', 'show_why', 'show_testimonials'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        unset($data['logo'], $data['remove_logo'], $data['hero_image'], $data['remove_hero_image']);

        $tenant->update($data);

        return redirect()->route('admin.site.edit')
            ->with('success', 'Pengaturan situs disimpan.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
