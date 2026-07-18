@extends('layouts.platform')

@section('title', 'Syarat & Ketentuan - Lajur')

@section('content')
@include('legal._prose')

<section class="section" style="padding-top:56px">
    <div class="container">
        <div class="legal">
            <span class="eyebrow">Platform Lajur</span>
            <h1 class="section-title" style="margin-bottom:18px">Syarat &amp; Ketentuan</h1>

            <p class="legal-meta">
                Berlaku sejak {{ config('legal.effective_date') }}.<br>
                Layanan Lajur diselenggarakan oleh <strong>{{ config('legal.brand') }}</strong>,
                nama dagang dari usaha perorangan atas nama
                <strong>{{ config('legal.operator') }}</strong>, berkedudukan di
                {{ config('legal.city') }}, Indonesia (selanjutnya disebut &ldquo;kami&rdquo;).
            </p>

            <div class="toc">
                <ol>
                    <li><a href="#definisi">Definisi</a></li>
                    <li><a href="#layanan">Tentang layanan</a></li>
                    <li><a href="#akun">Akun dan tanggung jawab Anda</a></li>
                    <li><a href="#paket">Paket, harga, dan masa uji coba</a></li>
                    <li><a href="#pembayaran">Pembayaran dan perpanjangan</a></li>
                    <li><a href="#data-penyewa">Data penyewa Anda</a></li>
                    <li><a href="#larangan">Yang tidak boleh dilakukan</a></li>
                    <li><a href="#ketersediaan">Ketersediaan layanan</a></li>
                    <li><a href="#penangguhan">Penangguhan dan penghentian</a></li>
                    <li><a href="#tanggung-jawab">Batasan tanggung jawab</a></li>
                    <li><a href="#perubahan">Perubahan ketentuan</a></li>
                    <li><a href="#hukum">Hukum yang berlaku</a></li>
                    <li><a href="#kontak">Kontak</a></li>
                </ol>
            </div>

            <h2 id="definisi">1. Definisi</h2>
            <ul>
                <li><strong>Lajur</strong> — perangkat lunak manajemen usaha rental kendaraan berbasis langganan yang kami sediakan.</li>
                <li><strong>Anda</strong> atau <strong>Tenant</strong> — pemilik usaha rental yang mendaftar dan menggunakan Lajur untuk mengelola usahanya.</li>
                <li><strong>Penyewa</strong> — pelanggan Anda, yaitu orang yang menyewa kendaraan dari usaha Anda.</li>
                <li><strong>Konten Anda</strong> — seluruh data yang Anda masukkan ke Lajur: data armada, booking, driver, catatan BBM, dan data penyewa.</li>
            </ul>

            <h2 id="layanan">2. Tentang layanan</h2>
            <p>
                Lajur adalah alat bantu operasional. Kami menyediakan perangkat lunak untuk mencatat
                booking, mengelola armada dan driver, memantau konsumsi BBM, serta membuat laporan.
            </p>
            <p>
                <strong>Kami bukan pihak dalam transaksi sewa antara Anda dan penyewa Anda.</strong>
                Kami tidak menyewakan kendaraan, tidak menjamin pembayaran penyewa, dan tidak
                bertanggung jawab atas perjanjian sewa, kondisi kendaraan, kecelakaan, kehilangan,
                atau sengketa yang timbul di antara Anda dan penyewa Anda.
            </p>

            <h2 id="akun">3. Akun dan tanggung jawab Anda</h2>
            <ul>
                <li>Anda wajib memberikan data pendaftaran yang benar dan menjaganya tetap mutakhir.</li>
                <li>Anda bertanggung jawab penuh atas kerahasiaan kata sandi dan atas semua aktivitas yang terjadi di bawah akun Anda, termasuk aktivitas admin dan driver yang Anda beri akses.</li>
                <li>Anda menyatakan berumur minimal 18 tahun dan berwenang mengikatkan usaha Anda pada ketentuan ini.</li>
                <li>Segera hubungi kami bila Anda menduga akun Anda diakses pihak lain tanpa izin.</li>
            </ul>

            <h2 id="paket">4. Paket, harga, dan masa uji coba</h2>
            <p>
                Lajur tersedia dalam beberapa paket berlangganan dengan fitur dan harga berbeda.
                <strong>Harga dan isi tiap paket yang berlaku adalah yang tercantum pada
                <a href="{{ route('signup.pricing') }}">halaman Paket</a> pada saat Anda berlangganan.</strong>
                Harga ditampilkan dalam Rupiah dan sudah termasuk pajak bila berlaku.
            </p>
            <p>
                Bila kami menawarkan masa uji coba gratis, durasinya adalah yang tercantum pada halaman
                Paket saat Anda mendaftar. Selama masa uji coba Anda dapat berhenti kapan saja tanpa
                biaya. Setelah masa uji coba berakhir dan tidak ada pembayaran yang kami terima,
                akun Anda otomatis berpindah ke paket Basic — akun dan data Anda tidak dihapus.
            </p>

            <h2 id="pembayaran">5. Pembayaran dan perpanjangan</h2>
            <div class="callout">
                <p>
                    <strong>Tidak ada perpanjangan otomatis.</strong> Kami tidak menyimpan data kartu Anda
                    dan tidak pernah menagih otomatis. Setiap periode langganan harus Anda bayar sendiri.
                </p>
            </div>
            <ul>
                <li>Satu kali pembayaran berlaku untuk <strong>30 hari</strong> terhitung sejak pembayaran kami terima.</li>
                <li>Pembayaran diproses oleh <strong>Midtrans</strong>, penyedia pembayaran pihak ketiga. Data pembayaran Anda diproses langsung oleh mereka dan tunduk pada ketentuan mereka.</li>
                <li>Bila periode langganan berakhir tanpa perpanjangan, akun Anda turun ke paket Basic. Fitur yang hanya tersedia di paket lebih tinggi akan berhenti dapat diakses, namun data Anda tetap tersimpan.</li>
                <li>Pembayaran yang sudah masuk tidak dapat dikembalikan, kecuali diwajibkan oleh hukum atau kami menyatakan lain secara tertulis.</li>
            </ul>

            <h2 id="data-penyewa">6. Data penyewa Anda</h2>
            <p>
                Anda memasukkan data pribadi penyewa (nama, nomor telepon, email) ke dalam Lajur.
                Terhadap data tersebut, <strong>Anda adalah pengendali data dan kami adalah pemroses
                data</strong> yang bertindak atas instruksi Anda.
            </p>
            <p>Karena itu, menjadi tanggung jawab Anda untuk:</p>
            <ul>
                <li>memiliki dasar yang sah untuk mengumpulkan dan memasukkan data penyewa ke Lajur;</li>
                <li>memberitahu penyewa Anda bahwa datanya diproses melalui sistem kami;</li>
                <li>menanggapi permintaan penyewa atas hak-hak mereka terhadap data pribadinya.</li>
            </ul>
            <p>
                Konten Anda tetap milik Anda. Kami tidak menjual data Anda maupun data penyewa Anda.
                Rincian pemrosesan ada di <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>.
            </p>

            <h2 id="larangan">7. Yang tidak boleh dilakukan</h2>
            <ul>
                <li>Menggunakan Lajur untuk kegiatan melanggar hukum atau merugikan pihak lain.</li>
                <li>Memasukkan data pribadi orang lain tanpa dasar yang sah.</li>
                <li>Membagikan akses akun kepada pihak di luar usaha Anda, atau menjual kembali akses Lajur tanpa persetujuan tertulis kami.</li>
                <li>Mencoba membobol, memindai, membebani, atau merekayasa balik sistem kami; mengakses data tenant lain.</li>
                <li>Mengunggah program berbahaya atau konten yang melanggar hak pihak lain.</li>
            </ul>

            <h2 id="ketersediaan">8. Ketersediaan layanan</h2>
            <p>
                Kami berusaha menjaga Lajur tetap dapat diakses, tetapi <strong>kami tidak menjanjikan
                layanan bebas gangguan.</strong> Pemeliharaan, gangguan jaringan, atau kegagalan penyedia
                pihak ketiga dapat membuat layanan terhenti sementara. Kami tidak menjanjikan tingkat
                ketersediaan (SLA) tertentu kecuali disepakati tertulis secara terpisah.
            </p>
            <p>
                Fitur dapat berubah, ditambah, atau dihentikan. Bila suatu fitur berbayar kami hentikan
                secara permanen, kami akan memberi tahu Anda terlebih dahulu.
            </p>

            <h2 id="penangguhan">9. Penangguhan dan penghentian</h2>
            <p>
                Anda dapat berhenti kapan saja dengan tidak memperpanjang langganan, atau meminta
                penghapusan akun melalui email kami.
            </p>
            <p>
                Kami dapat menangguhkan atau menghentikan akses Anda bila Anda melanggar ketentuan ini,
                bila diwajibkan hukum, atau bila penggunaan Anda membahayakan sistem atau tenant lain.
                Untuk pelanggaran yang tidak berat, kami akan memberi peringatan dan kesempatan
                memperbaiki lebih dulu.
            </p>
            <p>
                Setelah akun dihentikan, data Anda kami perlakukan sesuai jangka waktu penyimpanan pada
                <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>. Anda dapat meminta salinan
                data Anda sebelum penghentian.
            </p>

            <h2 id="tanggung-jawab">10. Batasan tanggung jawab</h2>
            <p>
                Sejauh diizinkan hukum, tanggung jawab kami kepada Anda atas klaim apa pun yang timbul
                dari penggunaan Lajur <strong>dibatasi setinggi-tingginya sebesar jumlah yang Anda bayarkan
                kepada kami dalam 3 (tiga) bulan terakhir</strong> sebelum kejadian yang menimbulkan klaim.
            </p>
            <p>
                Kami tidak bertanggung jawab atas kehilangan keuntungan, kehilangan pendapatan, atau
                kerugian tidak langsung. Kami juga tidak bertanggung jawab atas keputusan bisnis yang Anda
                ambil berdasarkan laporan, indikator, atau penandaan anomali di dalam Lajur — semua itu
                adalah alat bantu, bukan pengganti pemeriksaan Anda sendiri.
            </p>

            <h2 id="perubahan">11. Perubahan ketentuan</h2>
            <p>
                Kami dapat memperbarui Syarat &amp; Ketentuan ini. Untuk perubahan yang material, kami
                akan memberi tahu melalui email ke alamat akun Anda atau pemberitahuan di dalam aplikasi,
                paling lambat 14 hari sebelum berlaku. Bila Anda tidak setuju, Anda dapat berhenti
                berlangganan sebelum tanggal berlaku.
            </p>

            <h2 id="hukum">12. Hukum yang berlaku</h2>
            <p>
                Ketentuan ini tunduk pada hukum Republik Indonesia. Bila timbul sengketa, kita
                mengutamakan penyelesaian secara musyawarah. Bila tidak tercapai, sengketa diselesaikan
                melalui Pengadilan Negeri di wilayah hukum {{ config('legal.city') }}.
            </p>

            <h2 id="kontak">13. Kontak</h2>
            <p>
                <strong>{{ config('legal.brand') }}</strong> — {{ config('legal.operator') }}<br>
                {{ config('legal.city') }}, Kalimantan Timur, Indonesia<br>
                <a href="mailto:{{ config('legal.email') }}">{{ config('legal.email') }}</a>
            </p>
        </div>
    </div>
</section>
@endsection
