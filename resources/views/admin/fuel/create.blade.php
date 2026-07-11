@extends('layouts.admin')

@section('title', 'Catat Pengisian BBM')
@section('crumb', 'Operasional / BBM')
@section('heading', 'Catat Pengisian BBM')

@section('content')
    <div class="panel">
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.fuel.store') }}" class="admin-form">
                @csrf

                <div class="field">
                    <label for="car_id">Mobil <span class="req">*</span></label>
                    <select class="input" id="car_id" name="car_id" required>
                        <option value="">— pilih mobil —</option>
                        @foreach ($cars as $c)
                            <option value="{{ $c->id }}" @selected(old('car_id') == $c->id)>
                                {{ $c->name }} {{ $c->plate_number ? '('.$c->plate_number.')' : '' }}{{ $c->tank_capacity_liters ? ' · tangki '.$c->tank_capacity_liters.' L' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="filled_at">Waktu pengisian <span class="req">*</span></label>
                    <input class="input" type="datetime-local" id="filled_at" name="filled_at" value="{{ old('filled_at', now()->format('Y-m-d\TH:i')) }}" required>
                </div>

                <div class="field">
                    <label for="liters">Liter <span class="req">*</span></label>
                    <input class="input" type="number" step="0.01" min="0.1" max="999" id="liters" name="liters" value="{{ old('liters') }}" required>
                </div>

                <div class="field">
                    <label for="price_per_liter">Harga per liter (Rp) <span class="req">*</span></label>
                    <input class="input" type="number" min="1" id="price_per_liter" name="price_per_liter" value="{{ old('price_per_liter') }}" required>
                </div>

                <div class="field">
                    <label for="total_cost">Total (Rp) — otomatis, boleh dikoreksi</label>
                    <input class="input" type="number" min="1" id="total_cost" name="total_cost" value="{{ old('total_cost') }}" placeholder="liter × harga">
                </div>

                <div class="field">
                    <label for="odometer_km">Odometer saat isi (km) — dianjurkan</label>
                    <input class="input" type="number" min="0" id="odometer_km" name="odometer_km" value="{{ old('odometer_km') }}" placeholder="mis. 45210">
                </div>

                <div class="field">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="full_tank" value="1" @checked(old('full_tank', '1'))>
                        Isi penuh (full tank) — dipakai untuk hitung konsumsi
                    </label>
                </div>

                <div class="field">
                    <label for="station">SPBU / lokasi</label>
                    <input class="input" type="text" id="station" name="station" value="{{ old('station') }}" maxlength="120" placeholder="mis. SPBU 64.751.02 Jl. Juanda">
                </div>

                <div class="field" style="grid-column:1/-1">
                    <label for="notes">Catatan</label>
                    <textarea class="input" id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                </div>

                <div style="grid-column:1/-1;display:flex;gap:12px">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('admin.fuel.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>

@push('scripts')
<script>
    // Total otomatis = liter × harga/L, selama admin belum mengetik manual.
    (function () {
        const liters = document.getElementById('liters');
        const price = document.getElementById('price_per_liter');
        const total = document.getElementById('total_cost');
        let touched = false;
        total.addEventListener('input', () => touched = true);
        function recalc() {
            if (touched) return;
            const l = parseFloat(liters.value), p = parseInt(price.value, 10);
            total.value = (l > 0 && p > 0) ? Math.round(l * p) : '';
        }
        liters.addEventListener('input', recalc);
        price.addEventListener('input', recalc);
    })();
</script>
@endpush
@endsection
