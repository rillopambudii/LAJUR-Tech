@extends('layouts.superadmin')

@section('title', 'Tenant')
@section('crumb', 'Super Admin')
@section('heading', 'Tenant')

@section('content')
@php
    $statusPill = [
        'trial' => 'pill-confirmed',
        'active' => 'pill-completed',
        'pending_payment' => 'pill-pending',
        'suspended' => 'pill-cancelled',
        'cancelled' => 'pill-cancelled',
    ];
    $statusLabel = [
        'trial' => 'Trial',
        'active' => 'Aktif',
        'pending_payment' => 'Menunggu Bayar',
        'suspended' => 'Ditangguhkan',
        'cancelled' => 'Dibatalkan',
    ];
@endphp

<div class="panel">
    <div class="panel-head">
        <h2>Tambah Tenant Baru</h2>
        <span class="tag">Trial 14 hari · plan Business</span>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('superadmin.tenants.store') }}">
            @csrf
            <div class="form-row">
                <div class="field" style="margin-bottom:0">
                    <label for="tenant-name">Nama Bisnis</label>
                    <input class="input" type="text" id="tenant-name" name="name" value="{{ old('name') }}" required placeholder="mis. Kaltim Rental Mobil">
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="tenant-slug">Slug</label>
                    <input class="input" type="text" id="tenant-slug" name="slug" value="{{ old('slug') }}" required placeholder="mis. kaltim-rental">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:18px">
                <x-icon name="plus" /> Buat Tenant
            </button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Semua Tenant</h2>
        <span class="tag">{{ $tenants->count() }} tenant</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Trial Berakhir</th>
                    <th>Langganan Berakhir</th>
                    <th>Ubah Plan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td>
                            <div class="cell-car" style="gap:0">
                                <div>
                                    <div class="nm">{{ $tenant->name }}</div>
                                    <div class="br">{{ $tenant->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="tag">{{ ucfirst($tenant->plan) }}</span></td>
                        <td>
                            <span class="pill {{ $statusPill[$tenant->subscription_status] ?? 'pill-no' }}">
                                {{ $statusLabel[$tenant->subscription_status] ?? $tenant->subscription_status }}
                            </span>
                        </td>
                        <td class="mono">{{ $tenant->trial_ends_at?->format('d M Y') ?? '-' }}</td>
                        <td class="mono">{{ $tenant->subscription_ends_at?->format('d M Y') ?? '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('superadmin.tenants.plan', $tenant) }}" style="display:flex;gap:8px;align-items:center">
                                @csrf @method('PATCH')
                                <select name="plan" class="select" style="width:auto;padding:8px 12px">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->key }}" {{ $tenant->plan === $plan->key ? 'selected' : '' }}>
                                            {{ $plan->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-ghost btn-sm">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">Belum ada tenant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
