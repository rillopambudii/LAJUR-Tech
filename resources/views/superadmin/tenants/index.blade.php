@extends('layouts.superadmin')

@section('title', 'Tenant')
@section('crumb', 'Super Admin')
@section('heading', 'Tenant')

@section('content')
<div style="margin-bottom:24px">
    <h2>Tambah tenant baru</h2>
    <form method="POST" action="{{ route('superadmin.tenants.store') }}">
        @csrf
        <label>Nama <input type="text" name="name" required></label>
        <label>Slug <input type="text" name="slug" required placeholder="mis. rental-baru"></label>
        <button type="submit">Buat tenant (trial 14 hari, Business)</button>
    </form>
</div>

<table class="table">
    <thead>
        <tr><th>Nama</th><th>Plan</th><th>Status</th><th>Trial berakhir</th><th>Ubah plan</th></tr>
    </thead>
    <tbody>
        @foreach ($tenants as $tenant)
            <tr>
                <td>{{ $tenant->name }}</td>
                <td>{{ $tenant->plan }}</td>
                <td>{{ $tenant->subscription_status }}</td>
                <td>{{ $tenant->trial_ends_at?->format('d M Y') ?? '-' }}</td>
                <td>
                    <form method="POST" action="{{ route('superadmin.tenants.plan', $tenant) }}" style="display:flex;gap:8px">
                        @csrf @method('PATCH')
                        <select name="plan">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->key }}" {{ $tenant->plan === $plan->key ? 'selected' : '' }}>
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit">Simpan</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
