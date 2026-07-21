@extends('layouts.public')

@section('title', $driver->name.' — Profil Driver — Lajur')

@push('head')
<style>
    .drvp-card{background:var(--white);border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;margin-bottom:24px}
    .drvp-banner{position:relative;height:104px;background:radial-gradient(120% 160% at 20% -20%,var(--petrol-600) 0%,var(--petrol) 60%,var(--petrol-700) 100%)}
    .drvp-head{display:flex;flex-direction:column;align-items:center;text-align:center;padding:0 24px 26px;margin-top:-52px}
    .drvp-head .avatar-lg{border:4px solid var(--white);box-shadow:0 10px 26px -8px rgba(15,27,51,.35)}
    .drvp-name{font-family:var(--font-display);font-weight:800;font-size:1.4rem;margin-top:14px}
    .drvp-overall{display:flex;align-items:center;gap:6px;margin-top:8px;color:var(--amber-600);font-weight:700}
    .drvp-overall svg{width:20px;height:20px}
    .drvp-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--ivory-200);border-top:1px solid var(--ivory-200)}
    .drvp-stat{background:var(--white);padding:16px;text-align:center}
    .drvp-stat .n{display:block;font-family:var(--font-mono);font-weight:700;font-size:1.3rem;color:var(--petrol)}
    .drvp-stat .l{font-size:.78rem;color:var(--graphite)}
    .drvp-breakdown{padding:20px 24px;border-top:1px solid var(--ivory-200);display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .drvp-breakdown .row{display:flex;justify-content:space-between;font-size:.9rem}
    .drvp-breakdown .row .k{color:var(--graphite)}
    .drvp-breakdown .row .v{font-weight:700;color:var(--petrol)}
    .drvp-review{padding:18px 24px;border-top:1px solid var(--ivory-200)}
    .drvp-review .who{font-weight:700;margin-bottom:4px}
    .drvp-review .stars{color:var(--amber);display:flex;gap:2px;margin-bottom:6px}
    .drvp-review .stars svg{width:14px;height:14px}
    .drvp-reply{margin-top:10px;padding:10px 14px;background:var(--ivory);border-radius:var(--radius);font-size:.88rem}
    .drvp-reply .lbl{font-weight:700;color:var(--petrol);margin-bottom:2px}
</style>
@endpush

@section('content')
<section class="section">
    <div class="container" style="max-width:640px">
        <div class="drvp-card">
            <div class="drvp-banner"></div>
            <div class="drvp-head">
                <x-avatar :user="$driver" size="lg" />
                <div class="drvp-name">{{ $driver->name }}</div>
                @if ($avgOverall !== null)
                    <div class="drvp-overall"><x-icon name="star" /> {{ number_format($avgOverall, 1) }} / 5</div>
                @endif
            </div>
            <div class="drvp-stats">
                <div class="drvp-stat"><span class="n">{{ $completedTrips }}</span><span class="l">Perjalanan Selesai</span></div>
                <div class="drvp-stat"><span class="n">{{ $reviews->total() }}</span><span class="l">Ulasan</span></div>
            </div>
            @if ($avgOverall !== null)
                <div class="drvp-breakdown">
                    <div class="row"><span class="k">Ketepatan Waktu</span><span class="v">{{ $avgPunctuality }}</span></div>
                    <div class="row"><span class="k">Kebersihan</span><span class="v">{{ $avgCleanliness }}</span></div>
                    <div class="row"><span class="k">Keramahan</span><span class="v">{{ $avgFriendliness }}</span></div>
                    <div class="row"><span class="k">Keamanan</span><span class="v">{{ $avgSafety }}</span></div>
                </div>
            @endif
        </div>

        @forelse ($reviews as $review)
            <div class="drvp-card">
                <div class="drvp-review">
                    <div class="stars" aria-label="{{ $review->rating_overall }} dari 5">
                        @for ($i = 0; $i < round($review->rating_overall); $i++)<x-icon name="star" />@endfor
                    </div>
                    <div class="who">{{ $review->maskedCustomerName() }}</div>
                    @if ($review->comment)
                        <p style="color:var(--graphite);margin:0">{{ $review->comment }}</p>
                    @endif
                    @if ($review->admin_reply)
                        <div class="drvp-reply">
                            <div class="lbl">Balasan dari Lajur</div>
                            <p style="margin:0">{{ $review->admin_reply }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="drvp-card">
                <div class="drvp-review" style="text-align:center;color:var(--graphite)">Belum ada ulasan untuk driver ini.</div>
            </div>
        @endforelse

        @if ($reviews->hasPages())
            {{ $reviews->links() }}
        @endif
    </div>
</section>
@endsection
