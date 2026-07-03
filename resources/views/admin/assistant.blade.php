@extends('layouts.admin')

@section('title', 'Asisten AI')
@section('crumb', 'AI')
@section('heading', 'Asisten AI')

@push('head')
<style>
    .ai-wrap{max-width:760px}
    .ai-intro{color:rgba(0,0,0,.6);margin:0 0 18px;line-height:1.6}
    .ai-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}
    .ai-chip{border:1px solid rgba(0,0,0,.12);background:#fff;border-radius:999px;padding:7px 14px;font-size:.85rem;cursor:pointer}
    .ai-chip:hover{border-color:var(--petrol,#0f5a5e);color:var(--petrol,#0f5a5e)}
    .ai-answer{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:20px 22px;margin-bottom:18px}
    .ai-q{font-weight:700;font-family:'Sora',sans-serif;margin:0 0 10px;display:flex;gap:8px;align-items:flex-start}
    .ai-a{white-space:pre-wrap;line-height:1.7}
    .ai-note{font-size:.82rem;color:rgba(0,0,0,.5);margin-top:14px}
    textarea.ai-input{width:100%;box-sizing:border-box;min-height:80px;padding:14px;border:1px solid rgba(0,0,0,.15);border-radius:12px;font:inherit;resize:vertical}
</style>
@endpush

@section('content')
<div class="ai-wrap">
    @unless ($configured)
        <div class="alert alert-error" role="alert" style="margin-bottom:18px">
            <x-icon name="alert" />
            <span>Asisten AI belum aktif. Setel kredensial AI di <code>.env</code> (mis. <code>AI_PROVIDER=openai</code> + <code>OPENAI_API_KEY</code> untuk Groq), lalu jalankan <code>php artisan config:clear</code>.</span>
        </div>
    @endunless

    <p class="ai-intro">Tanyakan apa saja tentang bisnis Anda dalam bahasa sehari-hari. Asisten menjawab berdasarkan data rental Anda sendiri — pendapatan, booking, okupansi armada, dan mobil terlaris.</p>

    <div class="ai-chips">
        @foreach ([
            'Pendapatan bulan ini berapa?',
            'Pendapatan bulan ini dibanding bulan lalu, naik berapa persen?',
            'Mobil apa yang paling laris bulan ini?',
            'Booking pending ada berapa dan siapa saja?',
            'Mobil mana yang pajak atau servisnya jatuh tempo?',
            'Bagaimana tren pendapatan 6 bulan terakhir?',
        ] as $example)
            <button type="button" class="ai-chip" data-fill="{{ $example }}">{{ $example }}</button>
        @endforeach
    </div>

    @if (session('assistant_answer'))
        <div class="ai-answer">
            <p class="ai-q"><x-icon name="chat" /> <span>{{ session('assistant_question') }}</span></p>
            <div class="ai-a">{{ session('assistant_answer') }}</div>
            <p class="ai-note">Jawaban dihasilkan AI dari data Anda. Selalu verifikasi angka penting di menu Laporan.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.assistant.ask') }}">
        @csrf
        <textarea class="ai-input" id="ai-question" name="question" placeholder="Contoh: Pendapatan bulan ini berapa?" maxlength="500" required>{{ old('question') }}</textarea>
        @error('question')<span class="field-error">{{ $message }}</span>@enderror
        <div style="margin-top:12px">
            <button type="submit" class="btn btn-primary" @disabled(! $configured)><x-icon name="sparkle" /> Tanya Asisten</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.ai-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var box = document.getElementById('ai-question');
            box.value = this.dataset.fill;
            box.focus();
        });
    });
</script>
@endpush
@endsection
