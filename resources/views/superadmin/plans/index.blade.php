@extends('layouts.superadmin')

@section('title', 'Plans & Fitur')
@section('crumb', 'Super Admin')
@section('heading', 'Plans & Fitur')

@section('content')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
    @foreach ($plans as $plan)
        <div class="card" style="padding:20px;border:1px solid #e2e2e2;border-radius:12px">
            <h2>{{ $plan->name }}</h2>

            <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" style="margin-bottom:16px">
                @csrf @method('PATCH')
                <label>Harga (Rp/bulan)
                    <input type="number" name="price" value="{{ $plan->price }}" min="0">
                </label>
                <label>Masa trial (hari)
                    <input type="number" name="trial_days" value="{{ $plan->trial_days }}" min="0">
                </label>
                <button type="submit">Simpan harga</button>
            </form>

            <form method="POST" action="{{ route('superadmin.plans.features', $plan) }}">
                @csrf @method('PATCH')
                @foreach ($features as $feature)
                    <label style="display:block">
                        <input type="checkbox" name="features[]" value="{{ $feature->id }}"
                            {{ $plan->features->contains('id', $feature->id) ? 'checked' : '' }}>
                        {{ $feature->name }}
                    </label>
                @endforeach
                <button type="submit">Simpan fitur</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
