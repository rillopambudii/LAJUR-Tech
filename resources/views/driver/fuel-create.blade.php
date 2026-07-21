@extends('layouts.driver')

@section('title', 'Catat BBM')

@section('content')
    <h1 style="font-family:'Sora',sans-serif;font-size:1.6rem;margin:6px 0 4px">Catat Pengisian BBM</h1>
    <p style="color:rgba(0,0,0,.55);margin:0 0 20px">Untuk mobil yang sedang jadi tugas Anda hari ini.</p>

    @if ($cars->isEmpty())
        <div class="drv-empty">
            <x-icon name="fuel" />
            <p>Tidak ada tugas yang sedang berjalan hari ini.<br>Pencatatan BBM hanya bisa untuk mobil yang sedang Anda bawa.</p>
        </div>
    @else
        <div class="panel">
            <div class="panel-body">
                <form method="POST" action="{{ route('driver.fuel.store') }}" enctype="multipart/form-data" class="admin-form">
                    @csrf

                    <div class="field">
                        <label for="car_id">Mobil <span class="req">*</span></label>
                        <select class="input @error('car_id') has-error @enderror" id="car_id" name="car_id" required>
                            <option value="">— pilih mobil —</option>
                            @foreach ($cars as $c)
                                <option value="{{ $c->id }}" @selected(old('car_id') == $c->id)>
                                    {{ $c->name }} {{ $c->plate_number ? '('.$c->plate_number.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('car_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="filled_at">Waktu pengisian <span class="req">*</span></label>
                        <input class="input" type="datetime-local" id="filled_at" name="filled_at" value="{{ old('filled_at', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    <div class="field">
                        <label for="liters">Liter <span class="req">*</span></label>
                        <input class="input @error('liters') has-error @enderror" type="number" step="0.01" min="0.1" max="999" id="liters" name="liters" value="{{ old('liters') }}" required>
                        @error('liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="price_per_liter">Harga per liter (Rp) <span class="req">*</span></label>
                        <input class="input @error('price_per_liter') has-error @enderror" type="number" min="1" id="price_per_liter" name="price_per_liter" value="{{ old('price_per_liter') }}" required>
                        @error('price_per_liter')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="odometer_km">Odometer saat isi (km) — dianjurkan</label>
                        <input class="input" type="number" min="0" id="odometer_km" name="odometer_km" value="{{ old('odometer_km') }}" placeholder="mis. 45210">
                    </div>

                    <div class="field">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="full_tank" value="1" @checked(old('full_tank', '1'))>
                            Isi penuh (full tank)
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

                    <div class="field" style="grid-column:1/-1">
                        <label for="receipt">Foto Struk <span class="req">*</span></label>
                        <input class="input @error('receipt') has-error @enderror" type="file" id="receipt" name="receipt" accept="image/*" capture="environment" required>
                        <small style="display:block;margin-top:6px;color:rgba(0,0,0,.5)">Wajib — bukti pengisian, maks 4 MB.</small>
                        @error('receipt')<span class="field-error">{{ $message }}</span>@enderror
                        <img id="receipt-preview" style="display:none;max-width:220px;border-radius:12px;margin-top:10px" alt="Preview struk">
                    </div>

                    <div style="grid-column:1/-1;display:flex;gap:12px">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('driver.dashboard') }}" class="btn btn-ghost">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('receipt');
        var preview = document.getElementById('receipt-preview');
        if (!input) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    })();
</script>
@endpush
