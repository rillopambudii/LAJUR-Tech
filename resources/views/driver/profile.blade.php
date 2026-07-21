@extends('layouts.driver')

@section('title', 'Profil Saya')

@section('content')
    <h1 style="font-family:'Sora',sans-serif;font-size:1.6rem;margin:6px 0 20px">Profil Saya</h1>

    <div class="prof-card">
        <div class="prof-banner"></div>
        <div class="prof-head">
            <x-avatar :user="$driver" size="lg" />
            <div class="prof-name">{{ $driver->name }}</div>
            <span class="prof-role"><x-icon name="route" /> Driver Lajur</span>
            <div class="prof-since">Bergabung sejak {{ $driver->created_at->translatedFormat('F Y') }}</div>
        </div>

        <div class="prof-stats">
            <div class="prof-stat">
                <span class="n">{{ $activeTrips }}</span>
                <span class="l">Tugas Aktif</span>
            </div>
            <div class="prof-stat">
                <span class="n">{{ $completedTrips }}</span>
                <span class="l">Perjalanan Selesai</span>
            </div>
        </div>

        <div class="prof-info">
            <div class="prof-row">
                <div class="ico"><x-icon name="mail" /></div>
                <div>
                    <div class="lbl">Email</div>
                    <div class="val">{{ $driver->email }}</div>
                </div>
            </div>
            <div class="prof-row">
                <div class="ico"><x-icon name="whatsapp" /></div>
                <div>
                    <div class="lbl">Nomor HP / WhatsApp</div>
                    <div class="val">{{ $driver->phone ?: 'Belum diisi' }}</div>
                </div>
            </div>
        </div>
    </div>

    <p style="text-align:center;color:var(--graphite);font-size:.86rem">
        Ingin ubah foto, email, atau nomor HP? Hubungi admin rental Anda.
    </p>
@endsection
