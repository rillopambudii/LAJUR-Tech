{{-- Loading screen bersama page induk + etalase tenant.
     Parameter: $name (nama brand), $logo (url logo atau null).
     Gaya di app.css (pakai var(--amber) → warna brand aktif).
     Ditaruh tepat setelah <body>. Cek sekali-per-sesi SEBELUM #splash agar tak berkedip. --}}
@php($name = $name ?? 'Lajur')
@php($logo = $logo ?? null)
@php($guard = $guard ?? true)
{{-- $guard=false: lewati penjaga sekali-per-sesi (dipakai splash dashboard yang
     dipicu flash 'greet' saat login — kemunculannya sudah dikontrol dari luar). --}}
@if ($guard)
<script>try{if(sessionStorage.getItem('lajurSplash')){document.documentElement.classList.add('no-splash');}else{sessionStorage.setItem('lajurSplash','1');}}catch(e){}</script>
@endif
<div id="splash" aria-hidden="true">
    <div class="splash-inner">
        <div class="splash-logo">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $name }}" class="splash-logo-img">
            @else
                <span class="splash-mark"><x-icon name="route" /></span>
            @endif
            {{ $name }}
        </div>
        <div class="splash-bar"><span></span></div>
    </div>
</div>
<script>
    (function(){
        var s=document.getElementById('splash');
        if(!s||document.documentElement.classList.contains('no-splash')){if(s)s.remove();return;}
        var start=Date.now(),MIN=650; // tampil minimal biar terasa disengaja, bukan berkedip
        function hide(){setTimeout(function(){s.classList.add('hide');setTimeout(function(){s.remove();},600);},Math.max(0,MIN-(Date.now()-start)));}
        if(document.readyState==='complete')hide();else window.addEventListener('load',hide);
        setTimeout(hide,3500); // jaring pengaman
    })();
</script>
