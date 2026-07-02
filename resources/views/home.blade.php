@extends('layouts.public')

@php $heroCar = $featured->first(); @endphp

@section('content')

{{-- ============ HERO ============ --}}
<section class="hero" id="home">
    <div class="container">
        <div class="hero-copy">
            <span class="eyebrow hero-eyebrow">Rental Mobil Premium · Kalimantan Timur</span>
            <h1>Perjalanan Anda, <span class="accent">dalam kendali penuh.</span></h1>
            <p>Sewa mobil premium yang terawat dengan harga transparan dan proses yang cepat. Dari dinas hingga liburan keluarga — Lajur siap mengantar.</p>
            <div class="hero-actions">
                <a href="#sewa" class="btn btn-primary">Lihat Armada <x-icon name="arrow-right" /></a>
                <a href="#cara" class="btn btn-light">Cara Sewa</a>
            </div>
            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-num">{{ $stats['cars'] }}+</span>
                    <span class="stat-label">Unit Siap Sewa</span>
                </div>
                <div class="stat">
                    <span class="stat-num">{{ max($stats['types'], 1) }}</span>
                    <span class="stat-label">Tipe Mobil</span>
                </div>
                <div class="stat">
                    <span class="stat-num">24/7</span>
                    <span class="stat-label">Dukungan</span>
                </div>
            </div>
        </div>

        <div class="hero-visual reveal">
            <div class="hero-card">
                <img src="{{ $heroCar?->image_url ?? asset('img/placeholder-car.svg') }}"
                     alt="{{ $heroCar ? $heroCar->brand.' '.$heroCar->name : 'Armada Lajur' }}" data-fallback>
                <div class="readout">
                    <div><span class="k">Unit</span><span class="v">{{ $heroCar?->name ?? 'Premium' }}</span></div>
                    <div><span class="k">Kursi</span><span class="v">{{ $heroCar?->seats ?? '—' }}</span></div>
                    <div><span class="k">Mulai</span><span class="v">Rp {{ $heroCar ? number_format($heroCar->price_per_day, 0, ',', '.') : '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ SEWA MOBIL ============ --}}
<section class="section" id="sewa">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Armada Kami</span>
            <h2 class="section-title">Pilih mobil yang pas untuk perjalanan Anda</h2>
            <p class="section-sub">Setiap unit ditampilkan lengkap dengan spesifikasinya — seperti membaca lembar data, transparan dan jelas.</p>
        </div>

        @if (session('booking_success'))
            <div class="alert alert-success" role="status">
                <x-icon name="check" /> <span>{{ session('booking_success') }}</span>
            </div>
        @endif

        @if ($types->count() > 1)
            <div class="filter-bar reveal" role="group" aria-label="Filter tipe mobil">
                <button class="chip is-active" data-filter="all" aria-pressed="true">Semua</button>
                @foreach ($types as $type)
                    <button class="chip" data-filter="{{ $type }}" aria-pressed="false">{{ $type }}</button>
                @endforeach
            </div>
        @endif

        @if ($cars->isEmpty())
            <div class="empty-state">
                <x-icon name="car" />
                <h3>Belum ada mobil tersedia</h3>
                <p>Armada sedang kami siapkan. Silakan cek kembali sebentar lagi.</p>
            </div>
        @else
            <div class="car-grid">
                @foreach ($cars as $car)
                    <x-car-card :car="$car" />
                @endforeach
            </div>
            <div class="empty-state" id="cars-empty" style="display:none; margin-top:24px;">
                <x-icon name="search" />
                <h3>Tidak ada mobil pada kategori ini</h3>
                <p>Coba pilih tipe lain atau lihat semua armada.</p>
            </div>
        @endif
    </div>
</section>

<hr class="road-divider">

{{-- ============ CARA SEWA ============ --}}
<section class="section" id="cara">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Cara Sewa</span>
            <h2 class="section-title">Empat langkah, langsung jalan</h2>
        </div>
        <div class="steps">
            @foreach ([
                ['Pilih Mobil', 'Jelajahi armada dan temukan unit yang sesuai kebutuhan & budget Anda.'],
                ['Isi Data & Tanggal', 'Masukkan data diri serta tanggal sewa. Estimasi harga muncul otomatis.'],
                ['Kirim Permintaan', 'Ajukan permintaan sewa. Tim kami akan menghubungi untuk konfirmasi.'],
                ['Berangkat', 'Setelah konfirmasi, mobil siap diantar atau diambil. Selamat jalan!'],
            ] as $i => $step)
                <div class="step reveal">
                    <div class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <h3>{{ $step[0] }}</h3>
                    <p>{{ $step[1] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ KENAPA LAJUR ============ --}}
<section class="section" id="kenapa" style="background:var(--ivory-200)">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Kenapa Lajur</span>
            <h2 class="section-title">Dibangun untuk rasa aman</h2>
        </div>
        <div class="features">
            @foreach ([
                ['shield', 'Armada Terawat', 'Setiap mobil diservis berkala dan dicek menyeluruh sebelum disewakan.'],
                ['tag', 'Harga Transparan', 'Tarif jelas per hari, tanpa biaya tersembunyi. Estimasi total dihitung di muka.'],
                ['clock', 'Proses Cepat', 'Ajukan sewa dalam hitungan menit. Tim responsif siap membantu kapan saja.'],
                ['sparkle', 'Layanan Premium', 'Pengalaman menyewa yang rapi dan profesional dari awal hingga akhir.'],
            ] as $f)
                <div class="feature reveal">
                    <div class="ico"><x-icon name="{{ $f[0] }}" /></div>
                    <h3>{{ $f[1] }}</h3>
                    <p>{{ $f[2] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ TESTIMONI ============ --}}
@if ($testimonials->isNotEmpty())
<section class="section" id="testimoni">
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow">Testimoni</span>
            <h2 class="section-title">Dipercaya pelanggan kami</h2>
        </div>
        <div class="testi-grid">
            @foreach ($testimonials as $t)
                <figure class="testi reveal">
                    <div class="stars" aria-label="Rating {{ $t->rating }} dari 5">
                        @for ($i = 0; $i < $t->rating; $i++)<x-icon name="star" />@endfor
                    </div>
                    <blockquote>“{{ $t->quote }}”</blockquote>
                    <figcaption class="who">
                        @if ($t->avatar_url)
                            <img src="{{ $t->avatar_url }}" alt="{{ $t->name }}" data-fallback>
                        @else
                            <span class="ph">{{ strtoupper(substr($t->name, 0, 1)) }}</span>
                        @endif
                        <span>
                            <span class="name">{{ $t->name }}</span>
                            @if ($t->role)<span class="role">{{ $t->role }}</span>@endif
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============ TENTANG ============ --}}
<section class="section" id="tentang" style="background:var(--ivory-200)">
    <div class="container">
        <div class="about">
            <div class="reveal">
                <span class="eyebrow">Tentang Kami</span>
                <h2 class="section-title">Mitra perjalanan Anda di Kalimantan Timur</h2>
                <p class="section-sub">Lajur lahir dari kebutuhan akan layanan rental mobil yang rapi, jujur, dan bisa diandalkan. Kami percaya menyewa mobil seharusnya semudah dan seaman membeli tiket — tanpa drama, tanpa biaya kejutan.</p>
                <ul class="about-points">
                    @foreach (['Armada beragam untuk setiap kebutuhan', 'Tim lokal yang memahami medan Kalimantan', 'Konfirmasi cepat & komunikasi yang jelas'] as $point)
                        <li><span class="tick"><x-icon name="check" /></span> <span>{{ $point }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="about-visual reveal">
                <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=900&q=70"
                     alt="Mobil premium Lajur" data-fallback loading="lazy">
            </div>
        </div>
    </div>
</section>

{{-- ============ KONTAK ============ --}}
<section class="section" id="kontak">
    <div class="container">
        <div class="contact">
            <div class="contact-info reveal">
                <span class="eyebrow">Kontak</span>
                <h2 class="section-title">Ada pertanyaan? Mari bicara.</h2>
                <p class="section-sub">Hubungi kami untuk pemesanan khusus, sewa jangka panjang, atau pertanyaan lainnya.</p>
                <div class="contact-item">
                    <span class="ico"><x-icon name="pin" /></span>
                    <span><span class="k">Lokasi</span><span class="v">Samarinda, Kalimantan Timur</span></span>
                </div>
                <div class="contact-item">
                    <span class="ico"><x-icon name="phone" /></span>
                    <span><span class="k">Telepon / WhatsApp</span><span class="v">+62 812-0000-0000</span></span>
                </div>
                <div class="contact-item">
                    <span class="ico"><x-icon name="mail" /></span>
                    <span><span class="k">Email</span><span class="v">halo@lajur.id</span></span>
                </div>
            </div>

            <div class="form-card reveal">
                @if (session('contact_success'))
                    <div class="alert alert-success" role="status">
                        <x-icon name="check" /> <span>{{ session('contact_success') }}</span>
                    </div>
                @endif
                @if ($errors->contact->any())
                    <div class="alert alert-error" role="alert">
                        <x-icon name="alert" />
                        <span>Mohon periksa kembali isian Anda.</span>
                    </div>
                @endif

                <form action="{{ route('contact.store') }}" method="POST" novalidate>
                    @csrf
                    <div class="hp-field" aria-hidden="true">
                        <label for="c-website">Jangan isi kolom ini</label>
                        <input type="text" id="c-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="c-name">Nama <span class="req">*</span></label>
                            <input class="input @error('name', 'contact') has-error @enderror" type="text" id="c-name" name="name" value="{{ old('name') }}" required>
                            @error('name', 'contact')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="c-email">Email <span class="req">*</span></label>
                            <input class="input @error('email', 'contact') has-error @enderror" type="email" id="c-email" name="email" value="{{ old('email') }}" required>
                            @error('email', 'contact')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="c-phone">Nomor HP</label>
                            <input class="input @error('phone', 'contact') has-error @enderror" type="tel" id="c-phone" name="phone" value="{{ old('phone') }}">
                            @error('phone', 'contact')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="c-subject">Subjek</label>
                            <input class="input" type="text" id="c-subject" name="subject" value="{{ old('subject') }}">
                        </div>
                    </div>
                    <div class="field">
                        <label for="c-message">Pesan <span class="req">*</span></label>
                        <textarea class="textarea @error('message', 'contact') has-error @enderror" id="c-message" name="message" required>{{ old('message') }}</textarea>
                        @error('message', 'contact')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Kirim Pesan</button>
                </form>
            </div>
        </div>
    </div>
</section>

{{-- ============ BOOKING MODAL ============ --}}
@php $reopen = $errors->booking->any(); @endphp
<div class="modal-backdrop @if($reopen) open @endif" id="booking-modal" data-close
     @if($reopen) data-reopen data-price="{{ old('_cprice', 0) }}" @endif>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="booking-title">
        <div class="modal-head">
            <h3 id="booking-title">Ajukan Sewa</h3>
            <button type="button" class="modal-close" data-close-btn aria-label="Tutup">
                <x-icon name="close" />
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-car">
                <img data-modal-img src="{{ asset('img/placeholder-car.svg') }}" alt="">
                <div>
                    <div class="name" data-modal-name>Mobil</div>
                    <div class="price" data-modal-price>—</div>
                </div>
            </div>

            @if ($errors->booking->any())
                <div class="alert alert-error" role="alert">
                    <x-icon name="alert" />
                    <span>
                        Permintaan gagal dikirim:
                        <ul>@foreach ($errors->booking->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </span>
                </div>
            @endif

            <form action="{{ route('booking.store') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="car_id" value="{{ old('car_id') }}">
                <input type="hidden" name="_cprice" value="{{ old('_cprice') }}" data-cprice>
                <div class="hp-field" aria-hidden="true">
                    <label for="b-website">Jangan isi kolom ini</label>
                    <input type="text" id="b-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="estimate">
                    <div>
                        <div class="lbl">Estimasi Total</div>
                        <div class="days" data-est-days>0 hari</div>
                    </div>
                    <div class="amount" data-est-amount>Rp 0</div>
                </div>

                <div class="field">
                    <label for="b-name">Nama Lengkap <span class="req">*</span></label>
                    <input class="input @error('customer_name', 'booking') has-error @enderror" type="text" id="b-name" name="customer_name" value="{{ old('customer_name') }}" required>
                    @error('customer_name', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="b-email">Email <span class="req">*</span></label>
                        <input class="input @error('customer_email', 'booking') has-error @enderror" type="email" id="b-email" name="customer_email" value="{{ old('customer_email') }}" required>
                        @error('customer_email', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="b-phone">Nomor HP <span class="req">*</span></label>
                        <input class="input @error('customer_phone', 'booking') has-error @enderror" type="tel" id="b-phone" name="customer_phone" value="{{ old('customer_phone') }}" required>
                        @error('customer_phone', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="b-start">Tanggal Mulai <span class="req">*</span></label>
                        <input class="input @error('start_date', 'booking') has-error @enderror" type="date" id="b-start" name="start_date" value="{{ old('start_date') }}" required>
                        @error('start_date', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="b-end">Tanggal Selesai <span class="req">*</span></label>
                        <input class="input @error('end_date', 'booking') has-error @enderror" type="date" id="b-end" name="end_date" value="{{ old('end_date') }}" required>
                        @error('end_date', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="field">
                    <label for="b-notes">Catatan (opsional)</label>
                    <textarea class="textarea" id="b-notes" name="notes" placeholder="Lokasi penjemputan, kebutuhan khusus, dll.">{{ old('notes') }}</textarea>
                    @error('notes', 'booking')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-block">Kirim Permintaan Sewa</button>
            </form>
        </div>
    </div>
</div>
@endsection
