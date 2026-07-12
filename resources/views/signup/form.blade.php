@extends('layouts.public')

@section('title', ($mode === 'trial' ? 'Coba Gratis 14 Hari' : 'Daftar '.$plan->name).' — Lajur')

@section('content')
<section class="section" style="padding-top:56px">
    <div class="container" style="max-width:980px">
        <div class="section-head reveal">
            <a href="{{ route('signup.pricing') }}" style="display:inline-flex;align-items:center;gap:6px;color:var(--graphite);font-size:.9rem;margin-bottom:14px">
                <x-icon name="arrow-right" style="transform:rotate(180deg);width:14px;height:14px" /> Kembali ke pilihan paket
            </a>
            <span class="eyebrow">
                @if ($mode === 'trial')
                    Langkah Terakhir
                @else
                    Paket {{ $plan->name }}
                @endif
            </span>
            <h1 class="section-title">
                @if ($mode === 'trial')
                    Mulai trial gratis 14 hari Anda
                @else
                    Daftar &amp; aktifkan paket {{ $plan->name }}
                @endif
            </h1>
            <p class="section-sub">
                @if ($mode === 'trial')
                    Cukup isi nama bisnis rental Anda — dashboard langsung aktif, tanpa kartu kredit.
                @else
                    Lengkapi data bisnis Anda, lalu lanjutkan ke pembayaran yang aman via Midtrans.
                @endif
            </p>
        </div>

        <div class="signup-layout reveal" style="margin-top:36px">
            <div class="form-card">
                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <x-icon name="alert" />
                        <span>Mohon periksa kembali isian Anda.</span>
                    </div>
                @endif

                <form method="POST" action="{{ $mode === 'trial' ? route('signup.trial.store') : route('signup.paid.store', $plan->key) }}" novalidate>
                    @csrf
                    <div class="field">
                        <label for="business_name">Nama Bisnis Rental <span class="req">*</span></label>
                        <input class="input @error('business_name') has-error @enderror" type="text" id="business_name" name="business_name"
                            value="{{ old('business_name') }}" placeholder="mis. Kaltim Rental Mobil" required autofocus>
                        @error('business_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="slug">Alamat Dashboard <span class="req">*</span></label>
                        <input class="input @error('slug') has-error @enderror" type="text" id="slug" name="slug"
                            value="{{ old('slug') }}" placeholder="mis. kaltim-rental" required>
                        <span style="display:block;margin-top:6px;font-size:.84rem;color:var(--graphite)">Dipakai sebagai identitas unik akun Anda — huruf kecil, angka, dan strip.</span>
                        @error('slug')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="owner_name">Nama Pemilik <span class="req">*</span></label>
                            <input class="input @error('owner_name') has-error @enderror" type="text" id="owner_name" name="owner_name"
                                value="{{ old('owner_name') }}" required>
                            @error('owner_name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="email">Email <span class="req">*</span></label>
                            <input class="input @error('email') has-error @enderror" type="email" id="email" name="email"
                                value="{{ old('email') }}" required autocomplete="email">
                            @error('email')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">Kata Sandi <span class="req">*</span></label>
                        <input class="input @error('password') has-error @enderror" type="password" id="password" name="password"
                            minlength="8" required autocomplete="new-password">
                        <span style="display:block;margin-top:6px;font-size:.84rem;color:var(--graphite)">Minimal 8 karakter.</span>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        @if ($mode === 'trial')
                            <x-icon name="arrow-right" /> Mulai Trial Gratis Sekarang
                        @else
                            <x-icon name="arrow-right" /> Lanjut ke Pembayaran
                        @endif
                    </button>
                    <p style="text-align:center;margin-top:14px;font-size:.84rem;color:var(--graphite)">
                        <x-icon name="shield" style="width:14px;height:14px;display:inline;vertical-align:-2px" />
                        Data Anda aman &amp; tidak dibagikan ke pihak lain.
                    </p>
                </form>
            </div>

            <div class="signup-side">
                @if ($mode === 'trial')
                    <div class="plan-card is-trial" style="padding:26px">
                        <h3 class="plan-name">Yang Anda dapatkan</h3>
                        <ul class="about-points">
                            <li><span class="tick"><x-icon name="check" /></span> 14 hari akses penuh, setara paket Business</li>
                            <li><span class="tick"><x-icon name="check" /></span> Booking, armada &amp; driver dalam satu dashboard</li>
                            <li><span class="tick"><x-icon name="check" /></span> Pelacakan GPS &amp; laporan BBM</li>
                            <li><span class="tick"><x-icon name="check" /></span> Asisten AI untuk ringkasan bisnis</li>
                            <li><span class="tick"><x-icon name="check" /></span> Tanpa kartu kredit, bisa berhenti kapan saja</li>
                        </ul>
                    </div>
                @else
                    <div class="estimate">
                        <div>
                            <div class="lbl">Paket dipilih</div>
                            <div style="font-weight:700;font-family:var(--font-display);font-size:1.1rem">{{ $plan->name }}</div>
                        </div>
                        <div class="amount">Rp {{ number_format($plan->price, 0, ',', '.') }}<span style="font-size:.7rem;font-weight:400;color:rgba(247,248,251,.7)"> /bln</span></div>
                    </div>
                    <div class="plan-card" style="padding:22px">
                        <p style="font-size:.9rem;color:var(--graphite);display:flex;align-items:flex-start;gap:10px">
                            <x-icon name="info" style="flex-shrink:0;width:18px;height:18px;color:var(--amber-600)" />
                            Setelah menekan tombol di samping, Anda akan diarahkan ke halaman pembayaran Midtrans yang aman untuk menyelesaikan transaksi.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
