@extends('layouts.admin')

@php $isEdit = $car->exists; @endphp

@section('title', $isEdit ? 'Edit Mobil' : 'Tambah Mobil')
@section('crumb', 'Manajemen / Mobil')
@section('heading', $isEdit ? 'Edit Mobil' : 'Tambah Mobil')

@section('topbar-action')
    <a href="{{ route('admin.cars.index') }}" class="btn btn-ghost">&larr; Kembali</a>
@endsection

@section('content')
<form action="{{ $isEdit ? route('admin.cars.update', $car) : route('admin.cars.store') }}"
      method="POST" enctype="multipart/form-data">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="admin-form">
        {{-- Main fields --}}
        <div class="panel">
            <div class="panel-body">
                <div class="form-row">
                    <div class="field">
                        <label for="name">Nama Mobil <span class="req">*</span></label>
                        <input class="input @error('name') has-error @enderror" id="name" name="name" value="{{ old('name', $car->name) }}" required>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="brand">Merek <span class="req">*</span></label>
                        <input class="input @error('brand') has-error @enderror" id="brand" name="brand" value="{{ old('brand', $car->brand) }}" required>
                        @error('brand')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="type">Tipe <span class="req">*</span></label>
                        <select class="select @error('type') has-error @enderror" id="type" name="type" required>
                            <option value="">— Pilih —</option>
                            @foreach (\App\Models\Car::TYPES as $t)
                                <option value="{{ $t }}" @selected(old('type', $car->type) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('type')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="seats">Jumlah Kursi <span class="req">*</span></label>
                        <input class="input @error('seats') has-error @enderror" type="number" id="seats" name="seats" min="1" max="20" value="{{ old('seats', $car->seats ?? 4) }}" required>
                        @error('seats')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="transmission">Transmisi <span class="req">*</span></label>
                        <select class="select @error('transmission') has-error @enderror" id="transmission" name="transmission" required>
                            @foreach (\App\Models\Car::TRANSMISSIONS as $t)
                                <option value="{{ $t }}" @selected(old('transmission', $car->transmission) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('transmission')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="fuel_type">Bahan Bakar <span class="req">*</span></label>
                        <select class="select @error('fuel_type') has-error @enderror" id="fuel_type" name="fuel_type" required>
                            @foreach (\App\Models\Car::FUEL_TYPES as $t)
                                <option value="{{ $t }}" @selected(old('fuel_type', $car->fuel_type) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('fuel_type')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="tank_capacity_liters">Kapasitas Tangki (L)</label>
                        <input class="input mono @error('tank_capacity_liters') has-error @enderror" type="number" min="1" max="1000" id="tank_capacity_liters" name="tank_capacity_liters" value="{{ old('tank_capacity_liters', $car->tank_capacity_liters) }}" placeholder="mis. 55">
                        @error('tank_capacity_liters')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="fuel_baseline_km_per_l">Konsumsi Normal (km/L)</label>
                        <input class="input mono @error('fuel_baseline_km_per_l') has-error @enderror" type="number" step="0.1" min="0.1" max="100" id="fuel_baseline_km_per_l" name="fuel_baseline_km_per_l" value="{{ old('fuel_baseline_km_per_l', $car->fuel_baseline_km_per_l) }}" placeholder="mis. 11.5">
                        @error('fuel_baseline_km_per_l')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="price_per_day">Harga / Hari (Rp) <span class="req">*</span></label>
                        <input class="input mono @error('price_per_day') has-error @enderror" id="price_per_day" name="price_per_day" inputmode="numeric" value="{{ old('price_per_day', $car->price_per_day) }}" placeholder="500000" required>
                        @error('price_per_day')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="sort_order">Urutan Tampil</label>
                        <input class="input @error('sort_order') has-error @enderror" type="number" id="sort_order" name="sort_order" min="0" value="{{ old('sort_order', $car->sort_order ?? 0) }}">
                        @error('sort_order')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="description">Deskripsi</label>
                    <textarea class="textarea" id="description" name="description">{{ old('description', $car->description) }}</textarea>
                    @error('description')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="plate_number">Nomor Polisi</label>
                        <input class="input @error('plate_number') has-error @enderror" id="plate_number" name="plate_number" value="{{ old('plate_number', $car->plate_number) }}" placeholder="KT 1234 AB">
                        @error('plate_number')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="tax_due_date">Jatuh Tempo Pajak (STNK)</label>
                        <input class="input @error('tax_due_date') has-error @enderror" type="date" id="tax_due_date" name="tax_due_date" value="{{ old('tax_due_date', optional($car->tax_due_date)->toDateString()) }}">
                        @error('tax_due_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="service_due_date">Jadwal Servis Berikutnya</label>
                        <input class="input @error('service_due_date') has-error @enderror" type="date" id="service_due_date" name="service_due_date" value="{{ old('service_due_date', optional($car->service_due_date)->toDateString()) }}">
                        @error('service_due_date')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Side: image + toggles + live preview --}}
        <div>
            <div class="panel">
                <div class="panel-head"><h2 style="font-size:1rem">Foto Mobil</h2></div>
                <div class="panel-body">
                    <img class="img-preview" data-image-preview data-preview-img
                         src="{{ $car->image_url ?? asset('img/placeholder-car.svg') }}" alt="Pratinjau foto">
                    <label class="file-drop">
                        <x-icon name="plus" style="width:20px;height:20px;margin:0 auto 6px" />
                        Pilih foto (JPG/PNG/WEBP, maks 2 MB)
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-input>
                    </label>
                    @error('image')<span class="field-error">{{ $message }}</span>@enderror

                    <div class="field" style="margin-top:14px">
                        <label for="image_url">atau URL Gambar</label>
                        <input class="input @error('image_url') has-error @enderror" id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="https://...">
                        @error('image_url')<span class="field-error">{{ $message }}</span>@enderror
                        @if ($isEdit && $car->image)
                            <small style="color:var(--graphite);display:block;margin-top:6px">Biarkan kosong untuk mempertahankan gambar saat ini.</small>
                        @endif
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body">
                    <div class="switch-row">
                        <span>Tersedia untuk disewa</span>
                        <label class="switch">
                            <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $car->is_available ?? true))>
                            <span class="track"></span>
                        </label>
                    </div>
                    <div class="switch-row">
                        <span>Tampilkan sebagai unggulan</span>
                        <label class="switch">
                            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $car->is_featured ?? false))>
                            <span class="track"></span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <x-icon name="check" /> {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Mobil' }}
            </button>
        </div>
    </div>
</form>
@endsection
