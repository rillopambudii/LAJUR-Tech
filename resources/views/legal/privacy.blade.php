@extends('layouts.platform')

@section('title', 'Kebijakan Privasi - Lajur')

@section('content')
@include('legal._prose')

<section class="section" style="padding-top:56px">
    <div class="container">
        <div class="legal">
            <span class="eyebrow">Platform Lajur</span>
            <h1 class="section-title" style="margin-bottom:18px">Kebijakan Privasi</h1>

            <p class="legal-meta">
                Berlaku sejak {{ config('legal.effective_date') }}.<br>
                Dikelola oleh <strong>{{ config('legal.brand') }}</strong>, nama dagang dari usaha
                perorangan atas nama <strong>{{ config('legal.operator') }}</strong>, berkedudukan di
                {{ config('legal.city') }}, Indonesia (&ldquo;kami&rdquo;).
            </p>

            <p>
                Kebijakan ini menjelaskan data apa yang kami kumpulkan, untuk apa, ke mana data itu
                mengalir, dan hak Anda atasnya. Kami berupaya mematuhi Undang-Undang No. 27 Tahun 2022
                tentang Pelindungan Data Pribadi.
            </p>

            <div class="toc">
                <ol>
                    <li><a href="#peran">Dua peran: pemilik rental dan penyewa</a></li>
                    <li><a href="#data">Data yang kami kumpulkan</a></li>
                    <li><a href="#tujuan">Untuk apa data digunakan</a></li>
                    <li><a href="#pihak-ketiga">Pihak ketiga yang menerima data</a></li>
                    <li><a href="#ai">Fitur Asisten AI</a></li>
                    <li><a href="#penyimpanan">Berapa lama data disimpan</a></li>
                    <li><a href="#keamanan">Keamanan</a></li>
                    <li><a href="#hak">Hak Anda atas data</a></li>
                    <li><a href="#anak">Anak di bawah umur</a></li>
                    <li><a href="#perubahan">Perubahan kebijakan</a></li>
                    <li><a href="#kontak">Kontak</a></li>
                </ol>
            </div>

            <h2 id="peran">1. Dua peran: pemilik rental dan penyewa</h2>
            <p>Ada dua kelompok orang yang datanya melewati Lajur, dan peran kami berbeda untuk masing-masing:</p>
            <ul>
                <li>
                    <strong>Pemilik rental (Tenant)</strong> — Anda yang mendaftar memakai Lajur. Untuk
                    data akun Anda, <strong>kami adalah pengendali data</strong>.
                </li>
                <li>
                    <strong>Penyewa</strong> — pelanggan dari usaha rental. Datanya dimasukkan oleh
                    pemilik rental. Untuk data ini, <strong>pemilik rental adalah pengendali data dan
                    kami hanya pemroses</strong> yang bertindak atas instruksinya. Bila Anda seorang
                    penyewa dan ingin menggunakan hak atas data Anda, hubungilah rental tempat Anda menyewa;
                    kami akan membantu mereka menindaklanjuti.
                </li>
            </ul>

            <h2 id="data">2. Data yang kami kumpulkan</h2>

            <h3>Dari pemilik rental</h3>
            <ul>
                <li>Nama pemilik dan nama usaha, alamat email, nomor telepon.</li>
                <li>Nama pengguna (subdomain) dan kata sandi (disimpan dalam bentuk ter-hash, tidak pernah sebagai teks asli).</li>
                <li>Paket langganan, status, dan riwayat pembayaran (referensi transaksi, bukan nomor kartu).</li>
                <li>Data teknis: alamat IP, jenis perangkat/peramban, dan log aktivitas untuk keamanan.</li>
            </ul>

            <h3>Yang dimasukkan pemilik rental tentang penyewa dan operasionalnya</h3>
            <ul>
                <li>Data penyewa: nama, nomor telepon, email, kode booking, serta jadwal dan riwayat sewa.</li>
                <li>Data armada dan operasional: kendaraan, driver, catatan pengisian BBM, odometer.</li>
                <li>
                    Data lokasi kendaraan bila fitur pelacakan diaktifkan. Fitur ini menampilkan posisi
                    <strong>kendaraan</strong>, bukan melacak orang.
                </li>
            </ul>

            <h2 id="tujuan">3. Untuk apa data digunakan</h2>
            <ul>
                <li>Menyediakan dan menjalankan fitur Lajur yang Anda pakai.</li>
                <li>Memproses pendaftaran, langganan, dan pembayaran.</li>
                <li>Mengirim pemberitahuan penting: konfirmasi, invoice, pengingat masa langganan.</li>
                <li>Menjaga keamanan, mencegah penyalahgunaan, dan memenuhi kewajiban hukum.</li>
                <li>Memberikan dukungan saat Anda menghubungi kami.</li>
            </ul>
            <p>Kami <strong>tidak menjual data Anda maupun data penyewa Anda</strong> kepada siapa pun.</p>

            <h2 id="pihak-ketiga">4. Pihak ketiga yang menerima data</h2>
            <p>
                Agar Lajur berjalan, sebagian data diproses oleh penyedia layanan pihak ketiga. Mereka
                hanya menerima data seperlunya dan tunduk pada ketentuan serta kebijakan privasi
                masing-masing.
            </p>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Penyedia</th><th>Untuk apa</th><th>Data yang terlibat</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Midtrans</strong></td>
                            <td>Memproses pembayaran langganan</td>
                            <td>Nama, email, dan detail pembayaran Anda</td>
                        </tr>
                        <tr>
                            <td><strong>Google Maps</strong></td>
                            <td>Menampilkan peta pada halaman pelacakan</td>
                            <td>Koordinat lokasi kendaraan</td>
                        </tr>
                        <tr>
                            <td><strong>Penyedia model AI</strong><br>(Anthropic / Groq)</td>
                            <td>Menjalankan fitur Asisten AI — lihat bagian 5</td>
                            <td>Ringkasan data bisnis yang relevan dengan pertanyaan Anda</td>
                        </tr>
                        <tr>
                            <td><strong>Penyedia GPS / telematika</strong></td>
                            <td>Menerima posisi kendaraan bila pelacakan diaktifkan</td>
                            <td>Identitas perangkat dan koordinat kendaraan</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p>
                Sebagian penyedia ini memproses data di server luar negeri. Dengan menggunakan Lajur,
                Anda memahami bahwa data terkait dapat ditransfer ke luar wilayah Indonesia sebatas
                diperlukan untuk menjalankan layanan.
            </p>

            <h2 id="ai">5. Fitur Asisten AI</h2>
            <div class="callout">
                <p>
                    <strong>Bacalah bagian ini bila Anda memakai Asisten AI.</strong> Saat Anda mengajukan
                    pertanyaan (misalnya &ldquo;pendapatan bulan ini berapa?&rdquo;), Lajur mengirimkan
                    <strong>ringkasan data bisnis yang relevan</strong> — seperti angka pendapatan, jumlah
                    booking, dan status armada — ke penyedia model AI pihak ketiga agar jawabannya dapat
                    disusun.
                </p>
            </div>
            <ul>
                <li>Yang dikirim hanya data yang relevan dengan pertanyaan, bukan seluruh basis data Anda.</li>
                <li>Asisten hanya membaca data melalui fungsi yang telah ditentukan; ia tidak dapat mengubah atau menghapus data Anda.</li>
                <li>Fitur ini <strong>aktif hanya bila diaktifkan</strong> pada paket Anda. Bila Anda tidak menggunakannya, tidak ada data yang dikirim ke penyedia AI.</li>
                <li>Kami tidak menggunakan data Anda untuk melatih model AI kami sendiri.</li>
            </ul>

            <h2 id="penyimpanan">6. Berapa lama data disimpan</h2>
            <ul>
                <li>
                    <strong>Data akun pemilik rental</strong> disimpan selama akun aktif dan selama masih
                    ada kewajiban hukum yang mengharuskannya.
                </li>
                <li>
                    <strong>Data booking dan penyewa</strong> disimpan hingga <strong>5 (lima) tahun</strong>
                    sejak booking terakhir. Jangka ini kami pilih agar selaras dengan kewajiban penyimpanan
                    dokumen keuangan dan pajak di Indonesia, sekaligus untuk kebutuhan bukti bila terjadi
                    sengketa.
                </li>
                <li>
                    Setelah jangka waktu berakhir, atau setelah permintaan penghapusan yang sah, data
                    dihapus atau dianonimkan, kecuali hukum mewajibkan kami menyimpannya lebih lama.
                </li>
            </ul>

            <h2 id="keamanan">7. Keamanan</h2>
            <p>
                Kami menjaga data dengan langkah yang wajar: kata sandi disimpan ter-hash, akses data
                dipisahkan antar-tenant, dan sambungan ke aplikasi menggunakan enkripsi. Meski demikian,
                tidak ada sistem yang sepenuhnya kebal. Anda turut menjaga keamanan dengan memakai kata
                sandi yang kuat dan tidak membagikannya.
            </p>
            <p>
                Bila terjadi kebocoran data pribadi yang berisiko merugikan Anda, kami akan memberi tahu
                Anda dan pihak berwenang sesuai ketentuan hukum yang berlaku.
            </p>

            <h2 id="hak">8. Hak Anda atas data</h2>
            <p>Sesuai UU Pelindungan Data Pribadi, Anda berhak untuk:</p>
            <ul>
                <li>mengetahui data apa yang kami proses dan meminta salinannya;</li>
                <li>memperbaiki data yang tidak akurat;</li>
                <li>meminta penghapusan data Anda, sepanjang tidak bertentangan dengan kewajiban hukum kami;</li>
                <li>menarik persetujuan atau membatasi pemrosesan tertentu;</li>
                <li>mengajukan keberatan atas pemrosesan tertentu.</li>
            </ul>
            <p>
                Untuk menggunakan hak ini, kirim email ke
                <a href="mailto:{{ config('legal.email') }}">{{ config('legal.email') }}</a>. Kami akan
                menanggapi dalam waktu wajar, umumnya paling lama 30 hari. Kami dapat meminta Anda
                membuktikan identitas terlebih dahulu demi keamanan.
            </p>

            <h2 id="anak">9. Anak di bawah umur</h2>
            <p>
                Lajur ditujukan untuk pelaku usaha dewasa. Kami tidak dengan sengaja mengumpulkan data
                anak di bawah 18 tahun. Bila Anda menduga hal itu terjadi, beri tahu kami agar dapat kami
                hapus.
            </p>

            <h2 id="perubahan">10. Perubahan kebijakan</h2>
            <p>
                Kebijakan ini dapat kami perbarui. Untuk perubahan yang material, kami akan memberi tahu
                melalui email atau pemberitahuan di dalam aplikasi sebelum berlaku. Tanggal berlaku di atas
                akan selalu menunjukkan versi terkini.
            </p>

            <h2 id="kontak">11. Kontak</h2>
            <p>
                Pertanyaan tentang privasi atau permintaan atas hak data Anda dapat ditujukan ke:
            </p>
            <p>
                <strong>{{ config('legal.brand') }}</strong> — {{ config('legal.operator') }}<br>
                {{ config('legal.city') }}, Kalimantan Timur, Indonesia<br>
                <a href="mailto:{{ config('legal.email') }}">{{ config('legal.email') }}</a>
            </p>
        </div>
    </div>
</section>
@endsection
