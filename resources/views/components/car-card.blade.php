@props(['car'])
<article class="car-card {{ $car->is_available ? '' : 'unavailable' }}" data-type="{{ $car->type }}">
    <div class="car-media">
        <img src="{{ $car->image_url ?? asset('img/placeholder-car.svg') }}"
             alt="{{ $car->brand }} {{ $car->name }}" loading="lazy" data-fallback>
        @if (! $car->is_available)
            <span class="car-badge muted">Tidak Tersedia</span>
        @elseif ($car->is_featured)
            <span class="car-badge">Unggulan</span>
        @endif
    </div>
    <div class="car-body">
        <div>
            <span class="car-brand">{{ $car->brand }} · {{ $car->type }}</span>
            <h3 class="car-name">{{ $car->name }}</h3>
        </div>

        <div class="spec-sheet" aria-label="Spesifikasi">
            <div class="cell">
                <span class="spec-key"><x-icon name="users" /> Kursi</span>
                <span class="spec-val">{{ $car->seats }}</span>
            </div>
            <div class="cell">
                <span class="spec-key"><x-icon name="gauge" /> Transmisi</span>
                <span class="spec-val">{{ $car->transmission === 'Automatic' ? 'AT' : 'MT' }}</span>
            </div>
            <div class="cell">
                <span class="spec-key"><x-icon name="fuel" /> BBM</span>
                <span class="spec-val">{{ $car->fuel_type }}</span>
            </div>
        </div>

        <div class="car-foot">
            <div class="car-price">
                <span class="price">Rp {{ number_format($car->price_per_day, 0, ',', '.') }}</span>
                <span class="per">/ hari</span>
            </div>
            @if ($car->is_available)
                <button type="button" class="btn btn-primary btn-sm" data-book
                        data-car-id="{{ $car->id }}"
                        data-car-name="{{ $car->brand }} {{ $car->name }}"
                        data-car-price="{{ $car->price_per_day }}"
                        data-car-image="{{ $car->image_url ?? asset('img/placeholder-car.svg') }}">
                    Sewa <x-icon name="arrow-right" />
                </button>
            @else
                <button type="button" class="btn btn-ghost btn-sm is-disabled" disabled aria-disabled="true">Tidak Tersedia</button>
            @endif
        </div>
    </div>
</article>
