@extends('layouts.admin')

@section('title', 'Pengaturan Situs')
@section('crumb', 'Situs Publik')
@section('heading', 'Pengaturan Situs')

@section('topbar-action')
    <a href="{{ $branding->siteUrl() }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">
        <x-icon name="eye" /> Lihat Situs
    </a>
@endsection

@php
    use App\Tenancy\Branding;
    $b = new Branding($tenant);
    $why = old('why_us', $b->whyUs());
    // Helper kecil untuk nilai lama/tersimpan.
    $val = fn ($field, $current) => old($field, $current);
@endphp

@push('head')
<style>
    .site-section { max-width: 820px; margin-bottom: 22px; }
    .site-section .panel-head p { margin-top: 4px; font-size: .88rem; color: var(--graphite); font-weight: 400; }
    .site-section .hint { font-size: .84rem; color: var(--graphite); margin-top: 6px; }
    .toggle-row { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border: 1px solid var(--ivory-200);
        border-radius: var(--radius); background: var(--ivory); margin-top: 4px; cursor: pointer; }
    .toggle-row input { margin-top: 3px; flex-shrink: 0; }
    .toggle-row strong { display: block; font-size: .95rem; }
    .toggle-row span { font-size: .84rem; color: var(--graphite); }
    .why-card { border: 1px solid var(--ivory-200); border-radius: var(--radius); padding: 14px 16px; margin-bottom: 12px; }
    .why-card .num { font-family: var(--font-mono); font-size: .78rem; color: var(--amber-600); font-weight: 700; margin-bottom: 8px; }
    .save-bar { position: sticky; bottom: 0; background: var(--white); border-top: 1px solid var(--ivory-200);
        padding: 16px 0; margin-top: 10px; max-width: 820px; display: flex; align-items: center; gap: 16px; z-index: 5; }
</style>
@endpush

@section('content')

@if (session('success'))
    <div class="alert alert-success" style="max-width:820px" role="status">
        <x-icon name="check" /> <span>{{ session('success') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-error" style="max-width:820px" role="alert">
        <x-icon name="alert" /> <span>Ada isian yang perlu diperiksa. Lihat tanda merah di bawah.</span>
    </div>
@endif

<p style="max-width:820px;color:var(--graphite);margin-bottom:20px">
    Semua pengaturan di halaman ini mengubah <strong>situs sewa publik Anda</strong> (yang dilihat pelanggan).
    Ubah seperlunya, lalu tekan <strong>Simpan Semua</strong> di bawah. Kolom yang dikosongkan memakai tampilan bawaan.
</p>

<form method="POST" action="{{ route('admin.site.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    {{-- ============ 1. IDENTITAS ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>1. Identitas &amp; Logo</h2><p>Nama, slogan, logo, dan warna khas bisnis Anda.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="display_name">Nama Tampilan</label>
                    <input class="input @error('display_name') has-error @enderror" type="text" id="display_name"
                        name="display_name" value="{{ $val('display_name', $tenant->display_name) }}" placeholder="{{ $tenant->name }}">
                    <span class="hint">Nama yang muncul di navbar &amp; footer situs.</span>
                    @error('display_name')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="tagline">Tagline</label>
                    <input class="input @error('tagline') has-error @enderror" type="text" id="tagline"
                        name="tagline" value="{{ $val('tagline', $tenant->tagline) }}" placeholder="Rental Mobil Premium · Kota Anda">
                    <span class="hint">Teks kecil di atas judul hero.</span>
                    @error('tagline')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="accent_color">Warna Aksen</label>
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="color" id="accent_color_picker" value="{{ $val('accent_color', $tenant->accent_color ?? '#E7B24C') }}"
                            style="width:46px;height:46px;border:1.5px solid var(--ivory-200);border-radius:10px;padding:2px;background:var(--white);cursor:pointer"
                            oninput="document.getElementById('accent_color').value = this.value">
                        <input class="input mono @error('accent_color') has-error @enderror" type="text" id="accent_color"
                            name="accent_color" value="{{ $val('accent_color', $tenant->accent_color) }}" placeholder="#E7B24C"
                            oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('accent_color_picker').value = this.value">
                    </div>
                    <span class="hint">Warna tombol &amp; sorotan di situs.</span>
                    @error('accent_color')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="logo">Logo (opsional)</label>
                    @if ($tenant->logo_path)
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:10px">
                            <div style="display:grid;place-items:center;height:64px;max-width:240px;padding:10px;border-radius:12px;border:1px solid var(--ivory-200);background:var(--ivory)">
                                <img src="{{ $b->logoUrl() }}" alt="Logo" style="max-height:100%;max-width:100%;object-fit:contain">
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:var(--graphite);cursor:pointer">
                                <input type="checkbox" name="remove_logo" value="1"> Hapus logo
                            </label>
                        </div>
                    @endif
                    <label for="logo" class="btn btn-ghost btn-sm" style="width:fit-content">
                        <x-icon name="edit" /> {{ $tenant->logo_path ? 'Ganti Logo' : 'Pilih Logo' }}
                    </label>
                    <input class="file-input-hidden" type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
                    <span class="hint">JPG/PNG/WEBP, maks 2 MB. Juga dipakai di splash screen.</span>
                    @error('logo')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ============ 2. HERO ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>2. Halaman Depan (Hero)</h2><p>Bagian paling atas situs — kesan pertama pelanggan.</p></div>
        <div class="panel-body">
            <div class="field">
                <label for="hero_image">Foto Latar Hero (opsional)</label>
                @if ($tenant->hero_image_path)
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:10px">
                        <img src="{{ $b->heroImageUrl() }}" alt="Hero" style="height:80px;width:140px;object-fit:cover;border-radius:12px;border:1px solid var(--ivory-200)">
                        <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:var(--graphite);cursor:pointer">
                            <input type="checkbox" name="remove_hero_image" value="1"> Hapus foto (pakai bawaan)
                        </label>
                    </div>
                @endif
                <label for="hero_image" class="btn btn-ghost btn-sm" style="width:fit-content">
                    <x-icon name="edit" /> {{ $tenant->hero_image_path ? 'Ganti Foto' : 'Pilih Foto' }}
                </label>
                <input class="file-input-hidden" type="file" id="hero_image" name="hero_image" accept=".jpg,.jpeg,.png,.webp">
                <span class="hint">Gambar lebar (mis. mobil Anda), maks 4 MB. Kosong = pakai foto bawaan.</span>
                @error('hero_image')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="hero_title">Judul Hero</label>
                <input class="input @error('hero_title') has-error @enderror" type="text" id="hero_title"
                    name="hero_title" value="{{ $val('hero_title', $tenant->hero_title) }}" placeholder="Perjalanan Anda, dalam kendali penuh.">
                @error('hero_title')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="hero_subtitle">Subjudul Hero</label>
                <textarea class="textarea @error('hero_subtitle') has-error @enderror" id="hero_subtitle" name="hero_subtitle" rows="2"
                    placeholder="Sewa mobil premium yang terawat...">{{ $val('hero_subtitle', $tenant->hero_subtitle) }}</textarea>
                @error('hero_subtitle')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    {{-- ============ 3. TENTANG ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>3. Tentang Kami</h2><p>Cerita singkat tentang bisnis rental Anda.</p></div>
        <div class="panel-body">
            <div class="field">
                <label for="about_title">Judul</label>
                <input class="input @error('about_title') has-error @enderror" type="text" id="about_title"
                    name="about_title" value="{{ $val('about_title', $tenant->about_title) }}" placeholder="Mitra perjalanan Anda di kota Anda">
                @error('about_title')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="about_text">Teks</label>
                <textarea class="textarea @error('about_text') has-error @enderror" id="about_text" name="about_text" rows="4"
                    placeholder="Ceritakan tentang bisnis Anda...">{{ $val('about_text', $tenant->about_text) }}</textarea>
                @error('about_text')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <label class="toggle-row">
                <input type="checkbox" name="show_about" value="1" @checked($val('show_about', $tenant->show_about ?? true))>
                <span><strong>Tampilkan bagian "Tentang Kami"</strong><span>Matikan untuk menyembunyikannya dari situs.</span></span>
            </label>
        </div>
    </div>

    {{-- ============ 4. KEUNGGULAN ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>4. Keunggulan ("Kenapa Kami")</h2><p>Alasan pelanggan memilih Anda. Isi maksimal 4; kosongkan judul untuk melewati.</p></div>
        <div class="panel-body">
            @for ($i = 0; $i < 4; $i++)
                <div class="why-card">
                    <div class="num">POIN {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="why_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="why_{{ $i }}_title" name="why_us[{{ $i }}][title]"
                                value="{{ data_get($why, "$i.title") }}" placeholder="mis. Armada Terawat">
                        </div>
                        <div class="field">
                            <label for="why_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="why_{{ $i }}_text" name="why_us[{{ $i }}][text]"
                                value="{{ data_get($why, "$i.text") }}" placeholder="Penjelasan singkat.">
                        </div>
                    </div>
                </div>
            @endfor
            <label class="toggle-row">
                <input type="checkbox" name="show_why" value="1" @checked($val('show_why', $tenant->show_why ?? true))>
                <span><strong>Tampilkan bagian "Keunggulan"</strong><span>Matikan untuk menyembunyikannya.</span></span>
            </label>
        </div>
    </div>

    {{-- ============ 5. KONTAK & WHATSAPP ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>5. Kontak &amp; WhatsApp</h2><p>Cara pelanggan menghubungi Anda. Nomor WhatsApp memunculkan tombol chat mengambang.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="whatsapp">Nomor WhatsApp</label>
                    <input class="input @error('whatsapp') has-error @enderror" type="text" id="whatsapp"
                        name="whatsapp" value="{{ $val('whatsapp', $tenant->whatsapp) }}" placeholder="0812-3456-7890">
                    <span class="hint">Boleh 08xx atau 62xx. Tombol "Chat WhatsApp" muncul jika diisi.</span>
                    @error('whatsapp')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="contact_phone">Telepon (tampil)</label>
                    <input class="input @error('contact_phone') has-error @enderror" type="text" id="contact_phone"
                        name="contact_phone" value="{{ $val('contact_phone', $tenant->contact_phone) }}" placeholder="+62 812-0000-0000">
                    @error('contact_phone')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="contact_email">Email</label>
                    <input class="input @error('contact_email') has-error @enderror" type="email" id="contact_email"
                        name="contact_email" value="{{ $val('contact_email', $tenant->contact_email) }}" placeholder="halo@bisnisanda.id">
                    @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="contact_address">Alamat</label>
                    <input class="input @error('contact_address') has-error @enderror" type="text" id="contact_address"
                        name="contact_address" value="{{ $val('contact_address', $tenant->contact_address) }}" placeholder="Kota, Provinsi">
                    @error('contact_address')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ============ 6. MEDIA SOSIAL ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>6. Media Sosial</h2><p>Muncul sebagai tautan di footer. Isi username atau URL lengkap.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="instagram">Instagram</label>
                    <input class="input" type="text" id="instagram" name="instagram" value="{{ $val('instagram', $tenant->instagram) }}" placeholder="@bisnisanda">
                </div>
                <div class="field">
                    <label for="tiktok">TikTok</label>
                    <input class="input" type="text" id="tiktok" name="tiktok" value="{{ $val('tiktok', $tenant->tiktok) }}" placeholder="@bisnisanda">
                </div>
            </div>
            <div class="field">
                <label for="facebook">Facebook</label>
                <input class="input" type="text" id="facebook" name="facebook" value="{{ $val('facebook', $tenant->facebook) }}" placeholder="facebook.com/bisnisanda">
            </div>
        </div>
    </div>

    {{-- ============ 7. TAMPILAN (FONT & UI) ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>7. Gaya Tampilan</h2><p>Font huruf dan bentuk sudut/spasi di seluruh situs.</p></div>
        <div class="panel-body">
            <label style="font-weight:600;display:block;margin-bottom:10px">Gaya Font</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:22px">
                @foreach ([
                    'klasik' => ["'Sora', system-ui, sans-serif", 'Klasik'], 'netral' => ["'Inter', system-ui, sans-serif", 'Netral'],
                    'ramah' => ["'Poppins', system-ui, sans-serif", 'Ramah'], 'elegan' => ["'Playfair Display', serif", 'Elegan'],
                    'korporat' => ["'Space Grotesk', system-ui, sans-serif", 'Korporat'],
                ] as $key => [$fontFamily, $label])
                    @php $cur = $val('font_style', $tenant->font_style ?? 'klasik'); @endphp
                    <label style="display:block;padding:14px;border:1.5px solid var(--ivory-200);border-radius:var(--radius);cursor:pointer;{{ $cur === $key ? 'border-color:var(--amber);background:var(--ivory)' : '' }}">
                        <input type="radio" name="font_style" value="{{ $key }}" @checked($cur === $key) style="margin-bottom:6px">
                        <div style="font-family:{{ $fontFamily }};font-size:1.1rem;font-weight:700">{{ $label }}</div>
                    </label>
                @endforeach
            </div>
            <label style="font-weight:600;display:block;margin-bottom:10px">Bentuk &amp; Spasi (Gaya UI)</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
                @foreach (['klasik'=>[14,'Klasik'],'tegas'=>[8,'Tegas'],'lembut'=>[18,'Lembut'],'minimalis'=>[4,'Minimalis'],'playful'=>[20,'Playful']] as $key => [$radius, $label])
                    @php $cur = $val('ui_style', $tenant->ui_style ?? 'klasik'); @endphp
                    <label style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:14px;border:1.5px solid var(--ivory-200);border-radius:var(--radius);cursor:pointer;{{ $cur === $key ? 'border-color:var(--amber);background:var(--ivory)' : '' }}">
                        <input type="radio" name="ui_style" value="{{ $key }}" @checked($cur === $key)>
                        <div style="width:52px;height:34px;background:var(--petrol);border-radius:{{ $radius }}px"></div>
                        <div style="font-weight:600;font-size:.9rem">{{ $label }}</div>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============ 8. EFEK & SPLASH ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>8. Efek Animasi &amp; Splash</h2><p>Animasi saat bagian muncul, dan layar pembuka berlogo.</p></div>
        <div class="panel-body">
            <label style="font-weight:600;display:block;margin-bottom:10px">Efek Munculnya Bagian</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px">
                @foreach ([
                    'fade-up' => 'Halus (dari bawah)', 'fade' => 'Lembut (memudar)', 'zoom' => 'Zoom (membesar)',
                    'slide' => 'Geser (dari samping)', 'none' => 'Tanpa efek',
                ] as $key => $label)
                    @php $cur = $val('section_effect', $tenant->section_effect ?? 'fade-up'); @endphp
                    <label style="display:block;padding:14px;border:1.5px solid var(--ivory-200);border-radius:var(--radius);cursor:pointer;{{ $cur === $key ? 'border-color:var(--amber);background:var(--ivory)' : '' }}">
                        <input type="radio" name="section_effect" value="{{ $key }}" @checked($cur === $key) style="margin-bottom:6px">
                        <div style="font-weight:600;font-size:.92rem">{{ $label }}</div>
                    </label>
                @endforeach
            </div>
            <label class="toggle-row">
                <input type="checkbox" name="splash_enabled" value="1" @checked($val('splash_enabled', $tenant->splash_enabled ?? true))>
                <span><strong>Tampilkan splash screen saat situs dibuka</strong><span>Layar pembuka singkat memakai logo Anda (atau nama bila belum ada logo).</span></span>
            </label>
        </div>
    </div>

    {{-- ============ 9. VISIBILITAS LAIN + SEO ============ --}}
    <div class="panel site-section">
        <div class="panel-head"><h2>9. Testimoni &amp; SEO</h2><p>Tampilkan testimoni dan atur bagaimana situs muncul di Google.</p></div>
        <div class="panel-body">
            <label class="toggle-row" style="margin-bottom:18px">
                <input type="checkbox" name="show_testimonials" value="1" @checked($val('show_testimonials', $tenant->show_testimonials ?? true))>
                <span><strong>Tampilkan bagian Testimoni</strong><span>Isi testimoni di menu <em>Testimoni</em>. Matikan bila belum ada.</span></span>
            </label>
            <div class="field">
                <label for="meta_title">Judul di Google (SEO)</label>
                <input class="input @error('meta_title') has-error @enderror" type="text" id="meta_title"
                    name="meta_title" value="{{ $val('meta_title', $tenant->meta_title) }}" placeholder="{{ $b->name() }} — Rental Mobil">
                @error('meta_title')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="meta_description">Deskripsi di Google (SEO)</label>
                <textarea class="textarea @error('meta_description') has-error @enderror" id="meta_description" name="meta_description" rows="2"
                    placeholder="Kalimat singkat yang muncul di hasil pencarian Google.">{{ $val('meta_description', $tenant->meta_description) }}</textarea>
                @error('meta_description')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="save-bar">
        <button type="submit" class="btn btn-primary">Simpan Semua Pengaturan</button>
        <a href="{{ $branding->siteUrl() }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><x-icon name="eye" /> Pratinjau Situs</a>
    </div>
</form>
@endsection
