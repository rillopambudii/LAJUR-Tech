@extends('layouts.superadmin')

@section('title', 'Konten Landing')
@section('crumb', 'Super Admin')
@section('heading', 'Konten Landing')

@section('content')
@php
    $v = fn (string $key, $default = '') => old($key, data_get($stored, $key, $default));
@endphp

<div class="panel">
    <div class="panel-head">
        <h2>Halaman Landing (lajur.id)</h2>
        <span class="tag"><a href="{{ url('/') }}" target="_blank" rel="noopener">Lihat Halaman &rarr;</a></span>
    </div>
    <div class="panel-body">
        <p style="color:var(--graphite);font-size:.92rem">
            Kosongkan field mana pun untuk memakai teks default (ditampilkan sebagai placeholder abu-abu).
            Hanya teks — ikon dan jumlah kartu per bagian tetap seperti sekarang.
        </p>
    </div>
</div>

<form method="POST" action="{{ route('superadmin.landing.update') }}">
    @csrf @method('PATCH')

    {{-- 1. HERO --}}
    <div class="panel">
        <div class="panel-head"><h2>1. Hero</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="hero_eyebrow">Label kecil di atas judul</label>
                <input class="input" type="text" id="hero_eyebrow" name="hero_eyebrow" value="{{ $v('hero_eyebrow') }}" placeholder="{{ $copy->heroEyebrow() }}">
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hero_title_lead">Judul baris 1</label>
                    <input class="input" type="text" id="hero_title_lead" name="hero_title_lead" value="{{ $v('hero_title_lead') }}" placeholder="{{ $copy->heroTitleLead() }}">
                </div>
                <div class="field">
                    <label for="hero_title_reveal">Judul baris 2 (warna aksen)</label>
                    <input class="input" type="text" id="hero_title_reveal" name="hero_title_reveal" value="{{ $v('hero_title_reveal') }}" placeholder="{{ $copy->heroTitleReveal() }}">
                </div>
            </div>
            <div class="field">
                <label for="hero_subtitle">Subjudul</label>
                <textarea class="input" id="hero_subtitle" name="hero_subtitle" rows="2" placeholder="{{ $copy->heroSubtitle() }}">{{ $v('hero_subtitle') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 2. TOMBOL CTA & EYEBROW SOROTAN (dipakai berulang) --}}
    <div class="panel">
        <div class="panel-head"><h2>2. Teks Berulang</h2><p>Dipakai di beberapa tempat sekaligus.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="cta_label">Teks tombol ajakan (muncul 5×: hero, 3 sorotan produk, CTA akhir)</label>
                    <input class="input" type="text" id="cta_label" name="cta_label" value="{{ $v('cta_label') }}" placeholder="{{ $copy->ctaLabel() }}">
                </div>
                <div class="field">
                    <label for="spotlight_eyebrow">Label kecil di 3 section sorotan produk</label>
                    <input class="input" type="text" id="spotlight_eyebrow" name="spotlight_eyebrow" value="{{ $v('spotlight_eyebrow') }}" placeholder="{{ $copy->spotlightEyebrow() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 3. TRUST STRIP ATAS --}}
    <div class="panel">
        <div class="panel-head"><h2>3. Baris Kepercayaan (bawah preview produk)</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="trust_lead">Kalimat pembuka</label>
                <input class="input" type="text" id="trust_lead" name="trust_lead" value="{{ $v('trust_lead') }}" placeholder="{{ $copy->trustLead() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->trustItems() as $i => $default)
                    <div class="field">
                        <label for="trust_items_{{ $i }}">Item {{ $i + 1 }}</label>
                        <input class="input" type="text" id="trust_items_{{ $i }}" name="trust_items[{{ $i }}]" value="{{ $v("trust_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 4. PAIN POINT --}}
    <div class="panel">
        <div class="panel-head"><h2>4. Masalah yang Diselesaikan</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="pain_eyebrow">Label kecil</label>
                    <input class="input" type="text" id="pain_eyebrow" name="pain_eyebrow" value="{{ $v('pain_eyebrow') }}" placeholder="{{ $copy->painEyebrow() }}">
                </div>
                <div class="field">
                    <label for="pain_title">Judul</label>
                    <input class="input" type="text" id="pain_title" name="pain_title" value="{{ $v('pain_title') }}" placeholder="{{ $copy->painTitle() }}">
                </div>
            </div>
            <div class="field">
                <label for="pain_subtitle">Subjudul</label>
                <textarea class="input" id="pain_subtitle" name="pain_subtitle" rows="2" placeholder="{{ $copy->painSubtitle() }}">{{ $v('pain_subtitle') }}</textarea>
            </div>
            @foreach ($copy->painItems() as $i => $default)
                <div class="why-card">
                    <div class="num">KARTU {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="pain_items_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="pain_items_{{ $i }}_title" name="pain_items[{{ $i }}][title]" value="{{ $v("pain_items.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="pain_items_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="pain_items_{{ $i }}_text" name="pain_items[{{ $i }}][text]" value="{{ $v("pain_items.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="field">
                <label for="pain_closing">Kalimat penutup (di bawah panah)</label>
                <input class="input" type="text" id="pain_closing" name="pain_closing" value="{{ $v('pain_closing') }}" placeholder="{{ $copy->painClosing() }}">
            </div>
        </div>
    </div>

    {{-- 5. SEBELUM / SESUDAH --}}
    <div class="panel">
        <div class="panel-head"><h2>5. Sebelum / Sesudah</h2></div>
        <div class="panel-body">
            <div class="form-row">
                @foreach ($copy->beforeItems() as $i => $default)
                    <div class="field">
                        <label for="before_items_{{ $i }}">Sebelum {{ $i + 1 }}</label>
                        <input class="input" type="text" id="before_items_{{ $i }}" name="before_items[{{ $i }}]" value="{{ $v("before_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="after_brand">Label brand "sesudah"</label>
                    <input class="input" type="text" id="after_brand" name="after_brand" value="{{ $v('after_brand') }}" placeholder="{{ $copy->afterBrand() }}">
                </div>
                <div class="field">
                    <label for="after_text">Teks "sesudah"</label>
                    <input class="input" type="text" id="after_text" name="after_text" value="{{ $v('after_text') }}" placeholder="{{ $copy->afterText() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 6. FITUR --}}
    <div class="panel">
        <div class="panel-head"><h2>6. Fitur (4 Kelompok)</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="features_title">Judul</label>
                    <input class="input" type="text" id="features_title" name="features_title" value="{{ $v('features_title') }}" placeholder="{{ $copy->featuresTitle() }}">
                </div>
                <div class="field">
                    <label for="features_subtitle">Subjudul</label>
                    <input class="input" type="text" id="features_subtitle" name="features_subtitle" value="{{ $v('features_subtitle') }}" placeholder="{{ $copy->featuresSubtitle() }}">
                </div>
            </div>
            @foreach ($copy->featureGroups() as $gi => $group)
                <div class="why-card">
                    <div class="num">KELOMPOK {{ $gi + 1 }}</div>
                    <div class="field">
                        <label for="feature_groups_{{ $gi }}_title">Judul kelompok</label>
                        <input class="input" type="text" id="feature_groups_{{ $gi }}_title" name="feature_groups[{{ $gi }}][title]" value="{{ $v("feature_groups.$gi.title") }}" placeholder="{{ $group['title'] }}">
                    </div>
                    @foreach ($group['items'] as $ii => $defaultItem)
                        <div class="field">
                            <label for="feature_groups_{{ $gi }}_items_{{ $ii }}">Baris {{ $ii + 1 }}</label>
                            <input class="input" type="text" id="feature_groups_{{ $gi }}_items_{{ $ii }}" name="feature_groups[{{ $gi }}][items][{{ $ii }}]" value="{{ $v("feature_groups.$gi.items.$ii") }}" placeholder="{{ $defaultItem }}">
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    {{-- 7. SOROTAN BBM --}}
    <div class="panel">
        <div class="panel-head"><h2>7. Sorotan: BBM</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_fuel_title">Judul</label>
                <input class="input" type="text" id="spotlight_fuel_title" name="spotlight_fuel_title" value="{{ $v('spotlight_fuel_title') }}" placeholder="{{ $copy->spotlightFuelTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_fuel_text">Teks</label>
                <textarea class="input" id="spotlight_fuel_text" name="spotlight_fuel_text" rows="3" placeholder="{{ $copy->spotlightFuelText() }}">{{ $v('spotlight_fuel_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 8. SOROTAN NAVIGASI DRIVER --}}
    <div class="panel">
        <div class="panel-head"><h2>8. Sorotan: Navigasi Driver</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_driver_title">Judul</label>
                <input class="input" type="text" id="spotlight_driver_title" name="spotlight_driver_title" value="{{ $v('spotlight_driver_title') }}" placeholder="{{ $copy->spotlightDriverTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_driver_text">Teks</label>
                <textarea class="input" id="spotlight_driver_text" name="spotlight_driver_text" rows="3" placeholder="{{ $copy->spotlightDriverText() }}">{{ $v('spotlight_driver_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 9. SOROTAN REPUTASI & ULASAN DRIVER --}}
    <div class="panel">
        <div class="panel-head"><h2>9. Sorotan: Reputasi &amp; Ulasan Sopir</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="reviews_eyebrow">Label kecil (eyebrow)</label>
                    <input class="input" type="text" id="reviews_eyebrow" name="reviews_eyebrow" value="{{ $v('reviews_eyebrow') }}" placeholder="{{ $copy->reviewsEyebrow() }}">
                </div>
                <div class="field">
                    <label for="reviews_caption">Keterangan di bawah gambar</label>
                    <input class="input" type="text" id="reviews_caption" name="reviews_caption" value="{{ $v('reviews_caption') }}" placeholder="{{ $copy->reviewsCaption() }}">
                </div>
            </div>
            <div class="field">
                <label for="reviews_title">Judul</label>
                <input class="input" type="text" id="reviews_title" name="reviews_title" value="{{ $v('reviews_title') }}" placeholder="{{ $copy->reviewsTitle() }}">
            </div>
            <div class="field">
                <label for="reviews_text">Teks</label>
                <textarea class="input" id="reviews_text" name="reviews_text" rows="3" placeholder="{{ $copy->reviewsText() }}">{{ $v('reviews_text') }}</textarea>
            </div>
            @foreach ($copy->reviewsItems() as $i => $default)
                <div class="field">
                    <label for="reviews_items_{{ $i }}">Poin {{ $i + 1 }}</label>
                    <input class="input" type="text" id="reviews_items_{{ $i }}" name="reviews_items[{{ $i }}]" value="{{ $v("reviews_items.$i") }}" placeholder="{{ $default }}">
                </div>
            @endforeach
        </div>
    </div>

    {{-- 10. HIGHLIGHT KELUARGA --}}
    <div class="panel">
        <div class="panel-head"><h2>10. Highlight: Keluarga Memantau</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="family_title">Judul</label>
                    <input class="input" type="text" id="family_title" name="family_title" value="{{ $v('family_title') }}" placeholder="{{ $copy->familyTitle() }}">
                </div>
                <div class="field">
                    <label for="family_subtitle">Subjudul</label>
                    <input class="input" type="text" id="family_subtitle" name="family_subtitle" value="{{ $v('family_subtitle') }}" placeholder="{{ $copy->familySubtitle() }}">
                </div>
            </div>
            @foreach ($copy->familySteps() as $i => $default)
                <div class="why-card">
                    <div class="num">LANGKAH {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="family_steps_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="family_steps_{{ $i }}_title" name="family_steps[{{ $i }}][title]" value="{{ $v("family_steps.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="family_steps_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="family_steps_{{ $i }}_text" name="family_steps[{{ $i }}][text]" value="{{ $v("family_steps.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="form-row">
                <div class="field">
                    <label for="family_chat_caption">Keterangan ilustrasi chat</label>
                    <input class="input" type="text" id="family_chat_caption" name="family_chat_caption" value="{{ $v('family_chat_caption') }}" placeholder="{{ $copy->familyChatCaption() }}">
                </div>
                <div class="field">
                    <label for="family_track_caption">Keterangan ilustrasi halaman lacak</label>
                    <input class="input" type="text" id="family_track_caption" name="family_track_caption" value="{{ $v('family_track_caption') }}" placeholder="{{ $copy->familyTrackCaption() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 10. SOROTAN ETALASE --}}
    <div class="panel">
        <div class="panel-head"><h2>11. Sorotan: Etalase Tenant</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="spotlight_storefront_title">Judul</label>
                <input class="input" type="text" id="spotlight_storefront_title" name="spotlight_storefront_title" value="{{ $v('spotlight_storefront_title') }}" placeholder="{{ $copy->spotlightStorefrontTitle() }}">
            </div>
            <div class="field">
                <label for="spotlight_storefront_text">Teks</label>
                <textarea class="input" id="spotlight_storefront_text" name="spotlight_storefront_text" rows="3" placeholder="{{ $copy->spotlightStorefrontText() }}">{{ $v('spotlight_storefront_text') }}</textarea>
            </div>
        </div>
    </div>

    {{-- 11. PREVIEW GPS --}}
    <div class="panel">
        <div class="panel-head"><h2>12. Preview GPS</h2><p>Section ilustrasi berlabel "segera hadir" — jangan hapus label ini, fitur belum berjalan.</p></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="gps_badge">Label badge</label>
                    <input class="input" type="text" id="gps_badge" name="gps_badge" value="{{ $v('gps_badge') }}" placeholder="{{ $copy->gpsBadge() }}">
                </div>
                <div class="field">
                    <label for="gps_title">Judul</label>
                    <input class="input" type="text" id="gps_title" name="gps_title" value="{{ $v('gps_title') }}" placeholder="{{ $copy->gpsTitle() }}">
                </div>
            </div>
            <div class="field">
                <label for="gps_text">Teks</label>
                <textarea class="input" id="gps_text" name="gps_text" rows="2" placeholder="{{ $copy->gpsText() }}">{{ $v('gps_text') }}</textarea>
            </div>
            <div class="field">
                <label for="gps_note">Catatan kecil</label>
                <input class="input" type="text" id="gps_note" name="gps_note" value="{{ $v('gps_note') }}" placeholder="{{ $copy->gpsNote() }}">
            </div>
        </div>
    </div>

    {{-- 12. KENAPA LAJUR --}}
    <div class="panel">
        <div class="panel-head"><h2>13. Kenapa Memilih Lajur</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="why_title">Judul</label>
                <input class="input" type="text" id="why_title" name="why_title" value="{{ $v('why_title') }}" placeholder="{{ $copy->whyTitle() }}">
            </div>
            @foreach ($copy->whyItems() as $i => $default)
                <div class="why-card">
                    <div class="num">KARTU {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="why_items_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="why_items_{{ $i }}_title" name="why_items[{{ $i }}][title]" value="{{ $v("why_items.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="why_items_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="why_items_{{ $i }}_text" name="why_items[{{ $i }}][text]" value="{{ $v("why_items.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 13. WORKFLOW --}}
    <div class="panel">
        <div class="panel-head"><h2>14. Alur Mulai (Workflow)</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="workflow_title">Judul</label>
                <input class="input" type="text" id="workflow_title" name="workflow_title" value="{{ $v('workflow_title') }}" placeholder="{{ $copy->workflowTitle() }}">
            </div>
            @foreach ($copy->workflowSteps() as $i => $default)
                <div class="why-card">
                    <div class="num">LANGKAH {{ $i + 1 }}</div>
                    <div class="form-row">
                        <div class="field">
                            <label for="workflow_steps_{{ $i }}_title">Judul</label>
                            <input class="input" type="text" id="workflow_steps_{{ $i }}_title" name="workflow_steps[{{ $i }}][title]" value="{{ $v("workflow_steps.$i.title") }}" placeholder="{{ $default['title'] }}">
                        </div>
                        <div class="field">
                            <label for="workflow_steps_{{ $i }}_text">Keterangan</label>
                            <input class="input" type="text" id="workflow_steps_{{ $i }}_text" name="workflow_steps[{{ $i }}][text]" value="{{ $v("workflow_steps.$i.text") }}" placeholder="{{ $default['text'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 14. ECOSYSTEM --}}
    <div class="panel">
        <div class="panel-head"><h2>15. Platform Ecosystem</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="ecosystem_title_line1">Judul baris 1</label>
                    <input class="input" type="text" id="ecosystem_title_line1" name="ecosystem_title_line1" value="{{ $v('ecosystem_title_line1') }}" placeholder="{{ $copy->ecosystemTitleLine1() }}">
                </div>
                <div class="field">
                    <label for="ecosystem_title_line2">Judul baris 2</label>
                    <input class="input" type="text" id="ecosystem_title_line2" name="ecosystem_title_line2" value="{{ $v('ecosystem_title_line2') }}" placeholder="{{ $copy->ecosystemTitleLine2() }}">
                </div>
            </div>
            <div class="field">
                <label for="ecosystem_subtitle">Subjudul</label>
                <input class="input" type="text" id="ecosystem_subtitle" name="ecosystem_subtitle" value="{{ $v('ecosystem_subtitle') }}" placeholder="{{ $copy->ecosystemSubtitle() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->ecosystemItems() as $i => $default)
                    <div class="field">
                        <label for="ecosystem_items_{{ $i }}">Item {{ $i + 1 }}</label>
                        <input class="input" type="text" id="ecosystem_items_{{ $i }}" name="ecosystem_items[{{ $i }}]" value="{{ $v("ecosystem_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
            <div class="field">
                <label for="ecosystem_caption">Kalimat penutup</label>
                <input class="input" type="text" id="ecosystem_caption" name="ecosystem_caption" value="{{ $v('ecosystem_caption') }}" placeholder="{{ $copy->ecosystemCaption() }}">
            </div>
        </div>
    </div>

    {{-- 15. HARGA --}}
    <div class="panel">
        <div class="panel-head"><h2>16. Harga</h2></div>
        <div class="panel-body">
            <div class="form-row">
                <div class="field">
                    <label for="pricing_title">Judul</label>
                    <input class="input" type="text" id="pricing_title" name="pricing_title" value="{{ $v('pricing_title') }}" placeholder="{{ $copy->pricingTitle() }}">
                </div>
                <div class="field">
                    <label for="pricing_subtitle">Subjudul</label>
                    <input class="input" type="text" id="pricing_subtitle" name="pricing_subtitle" value="{{ $v('pricing_subtitle') }}" placeholder="{{ $copy->pricingSubtitle() }}">
                </div>
            </div>
        </div>
    </div>

    {{-- 16. CTA AKHIR --}}
    <div class="panel">
        <div class="panel-head"><h2>17. Ajakan Penutup</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="cta_title">Judul</label>
                <input class="input" type="text" id="cta_title" name="cta_title" value="{{ $v('cta_title') }}" placeholder="{{ $copy->ctaTitle() }}">
            </div>
            <div class="field">
                <label for="cta_text">Teks</label>
                <input class="input" type="text" id="cta_text" name="cta_text" value="{{ $v('cta_text') }}" placeholder="{{ $copy->ctaText() }}">
            </div>
            <div class="form-row">
                @foreach ($copy->ctaTrustItems() as $i => $default)
                    <div class="field">
                        <label for="cta_trust_items_{{ $i }}">Baris kepercayaan {{ $i + 1 }}</label>
                        <input class="input" type="text" id="cta_trust_items_{{ $i }}" name="cta_trust_items[{{ $i }}]" value="{{ $v("cta_trust_items.$i") }}" placeholder="{{ $default }}">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-bottom:40px">
        <x-icon name="check" /> Simpan Semua Perubahan
    </button>
</form>
@endsection
