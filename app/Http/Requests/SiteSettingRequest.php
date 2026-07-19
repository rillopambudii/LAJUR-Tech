<?php

namespace App\Http\Requests;

use App\Tenancy\Branding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route already behind auth+admin middleware
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'font_style' => ['nullable', Rule::in(Branding::fontStyleKeys())],
            'ui_style' => ['nullable', Rule::in(Branding::uiStyleKeys())],

            // Hero
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],

            // Tentang
            'about_title' => ['nullable', 'string', 'max:255'],
            'about_text' => ['nullable', 'string', 'max:2000'],

            // Keunggulan (daftar judul + teks)
            'why_us' => ['nullable', 'array', 'max:6'],
            'why_us.*.title' => ['nullable', 'string', 'max:80'],
            'why_us.*.text' => ['nullable', 'string', 'max:300'],

            // Kontak & sosial
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],

            // Tampilan & efek
            'section_effect' => ['nullable', Rule::in(Branding::SECTION_EFFECTS)],
            'splash_enabled' => ['nullable', 'boolean'],

            // Visibilitas bagian
            'show_about' => ['nullable', 'boolean'],
            'show_why' => ['nullable', 'boolean'],
            'show_testimonials' => ['nullable', 'boolean'],

            // SEO
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => 'Warna harus format hex, mis. #E7B24C.',
            'logo.image' => 'Berkas harus berupa gambar.',
            'logo.mimes' => 'Logo harus berformat JPEG, JPG, PNG, atau WEBP.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'hero_image.image' => 'Foto hero harus berupa gambar.',
            'hero_image.max' => 'Ukuran foto hero maksimal 4 MB.',
        ];
    }
}
