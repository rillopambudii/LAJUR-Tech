@extends('layouts.superadmin')

@section('title', 'Plans & Fitur')
@section('crumb', 'Super Admin')
@section('heading', 'Plans & Fitur')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;align-items:start">
    @foreach ($plans as $plan)
        <div class="panel" style="margin-bottom:0">
            <div class="panel-head">
                <h2>{{ $plan->name }}</h2>
                <span class="tag">
                    @if ($plan->hasDiscount())
                        <s style="opacity:.6">Rp {{ number_format($plan->price, 0, ',', '.') }}</s>
                        Rp {{ number_format($plan->effectivePrice(), 0, ',', '.') }}/bln
                    @else
                        Rp {{ number_format($plan->price, 0, ',', '.') }}/bln
                    @endif
                </span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}">
                    @csrf @method('PATCH')
                    <div class="form-row">
                        <div class="field">
                            <label for="price-{{ $plan->id }}">Harga (Rp/bulan)</label>
                            <input class="input mono" type="number" id="price-{{ $plan->id }}" name="price" value="{{ $plan->price }}" min="0" required>
                        </div>
                        <div class="field">
                            <label for="trial-{{ $plan->id }}">Masa trial (hari)</label>
                            <input class="input mono" type="number" id="trial-{{ $plan->id }}" name="trial_days" value="{{ $plan->trial_days }}" min="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="disc-{{ $plan->id }}">Harga diskon (Rp/bulan)</label>
                            <input class="input mono" type="number" id="disc-{{ $plan->id }}" name="discount_price" value="{{ $plan->discount_price }}" min="0" placeholder="kosong = tanpa diskon">
                            <small style="color:var(--graphite);font-size:.78rem">Harga normal tampil dicoret; harga ini yang ditagihkan.</small>
                        </div>
                        <div class="field">
                            <label for="disclbl-{{ $plan->id }}">Label promo</label>
                            <input class="input" type="text" id="disclbl-{{ $plan->id }}" name="discount_label" value="{{ $plan->discount_label }}" maxlength="40" placeholder="mis. Promo Peluncuran">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-ghost btn-sm">Simpan Harga</button>
                </form>

                <form method="POST" action="{{ route('superadmin.plans.features', $plan) }}" style="margin-top:22px">
                    @csrf @method('PATCH')
                    <div style="font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--graphite);font-weight:600;margin-bottom:4px">Fitur Premium</div>
                    @foreach ($features as $feature)
                        <label class="switch-row" style="cursor:pointer">
                            <span style="font-size:.94rem">{{ $feature->name }}</span>
                            <span class="switch">
                                <input type="checkbox" name="features[]" value="{{ $feature->id }}"
                                    {{ $plan->features->contains('id', $feature->id) ? 'checked' : '' }}>
                                <span class="track"></span>
                            </span>
                        </label>
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:16px">Simpan Fitur</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
