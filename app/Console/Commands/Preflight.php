<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pemeriksaan konfigurasi sebelum go-live.
 *
 * Semua yang diperiksa di sini pernah nyaris lolos ke produksi: APP_DEBUG masih
 * true (stack trace + isi .env terpampang ke publik), kunci Midtrans produksi
 * dipasang tapi mode masih sandbox (pembayaran gagal diam-diam), MAIL_MAILER
 * masih 'log' (tautan lupa-password tak pernah sampai), dan cron belum dipasang
 * (trial tak pernah kedaluwarsa = kebocoran pendapatan).
 *
 * Jalankan di server SETELAH deploy: `php artisan lajur:preflight`
 */
class Preflight extends Command
{
    protected $signature = 'lajur:preflight';

    protected $description = 'Periksa kesiapan konfigurasi sebelum go-live';

    private array $problems = [];
    private array $warnings = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Pemeriksaan kesiapan produksi — Lajur</>');
        $this->newLine();

        $this->checkAppEnvironment();
        $this->checkMail();
        $this->checkPayment();
        $this->checkStorage();
        $this->checkDatabase();
        $this->checkBackup();
        $this->checkScheduler();

        $this->newLine();

        if ($this->problems === [] && $this->warnings === []) {
            $this->info('  Semua pemeriksaan lolos. Aman untuk go-live.');

            return self::SUCCESS;
        }

        if ($this->problems !== []) {
            $this->error('  '.count($this->problems).' MASALAH yang harus dibereskan sebelum go-live:');
            foreach ($this->problems as $p) {
                $this->line('    <fg=red>x</> '.$p);
            }
            $this->newLine();
        }

        if ($this->warnings !== []) {
            $this->warn('  '.count($this->warnings).' perlu diperiksa:');
            foreach ($this->warnings as $w) {
                $this->line('    <fg=yellow>!</> '.$w);
            }
        }

        $this->newLine();

        return $this->problems === [] ? self::SUCCESS : self::FAILURE;
    }

    private function ok(string $label, string $detail = ''): void
    {
        $this->line("  <fg=green>v</> {$label}".($detail ? " <fg=gray>({$detail})</>" : ''));
    }

    private function bad(string $label, string $problem): void
    {
        $this->line("  <fg=red>x</> {$label}");
        $this->problems[] = $problem;
    }

    private function warnAbout(string $label, string $warning): void
    {
        $this->line("  <fg=yellow>!</> {$label}");
        $this->warnings[] = $warning;
    }

    private function checkAppEnvironment(): void
    {
        if (config('app.debug')) {
            $this->bad('APP_DEBUG masih aktif', 'Set APP_DEBUG=false. Saat true, halaman error menampilkan stack trace BESERTA isi konfigurasi (password DB, API key) ke siapa pun pengunjung.');
        } else {
            $this->ok('APP_DEBUG mati');
        }

        if (config('app.env') !== 'production') {
            $this->bad('APP_ENV = '.config('app.env'), 'Set APP_ENV=production agar Laravel memakai perilaku & optimasi produksi.');
        } else {
            $this->ok('APP_ENV production');
        }

        $url = (string) config('app.url');
        if (Str::contains($url, ['localhost', '127.0.0.1'])) {
            $this->bad('APP_URL masih '.$url, 'Set APP_URL ke domain asli (mis. https://lajur.id). Nilai ini dipakai untuk tautan reset password, URL gambar, dan subdomain tenant — salah isi = tautan di email mengarah ke localhost.');
        } elseif (! Str::startsWith($url, 'https://')) {
            $this->warnAbout('APP_URL bukan https', 'APP_URL sebaiknya https:// agar cookie sesi aman dan tautan email tidak diblokir.');
        } else {
            $this->ok('APP_URL', $url);
        }

        if (empty(config('app.key'))) {
            $this->bad('APP_KEY kosong', 'Jalankan `php artisan key:generate` — tanpa ini sesi & data terenkripsi tak berfungsi.');
        } else {
            $this->ok('APP_KEY terisi');
        }
    }

    private function checkMail(): void
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->bad("MAIL_MAILER = {$mailer}", "MAIL_MAILER masih '{$mailer}' — email TIDAK benar-benar terkirim, hanya ditulis ke log. Akibatnya tautan lupa-password tak pernah sampai ke owner/admin dan invoice tak sampai ke pelanggan. Isi kredensial SMTP.");
        } else {
            $this->ok('MAIL_MAILER', $mailer);
        }

        $from = (string) config('mail.from.address');
        if ($from === '' || Str::contains($from, 'example.com')) {
            $this->bad('MAIL_FROM_ADDRESS masih contoh', 'Set MAIL_FROM_ADDRESS ke alamat domain sendiri. Alamat "example.com" hampir pasti ditolak/masuk spam.');
        } else {
            $this->ok('Alamat pengirim', $from);
        }
    }

    private function checkPayment(): void
    {
        if (config('services.payment.gateway') !== 'midtrans') {
            $this->warnAbout('Gateway pembayaran: '.config('services.payment.gateway'), 'PAYMENT_GATEWAY bukan midtrans — pembayaran langganan berjalan manual/offline.');

            return;
        }

        $server = (string) config('services.midtrans.server_key');
        $client = (string) config('services.midtrans.client_key');
        $isProduction = (bool) config('services.midtrans.is_production');

        if ($server === '' || $client === '') {
            $this->bad('Kunci Midtrans kosong', 'Isi MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY.');

            return;
        }

        // Kunci sandbox Midtrans berawalan "SB-", kunci produksi tidak.
        $serverIsSandboxKey = Str::startsWith($server, 'SB-');
        $clientIsSandboxKey = Str::startsWith($client, 'SB-');

        if ($serverIsSandboxKey !== $clientIsSandboxKey) {
            $this->bad('Kunci Midtrans campur', 'MIDTRANS_SERVER_KEY dan MIDTRANS_CLIENT_KEY berasal dari lingkungan berbeda (satu sandbox, satu produksi). Ambil keduanya dari dasbor yang sama.');

            return;
        }

        if ($isProduction && $serverIsSandboxKey) {
            $this->bad('Mode produksi memakai kunci sandbox', 'MIDTRANS_IS_PRODUCTION=true tapi kuncinya sandbox (awalan SB-). Pembayaran akan ditolak Midtrans.');
        } elseif (! $isProduction && ! $serverIsSandboxKey) {
            $this->bad('Mode sandbox memakai kunci produksi', 'MIDTRANS_IS_PRODUCTION=false tapi kuncinya kunci PRODUKSI (tanpa awalan SB-). Aplikasi menembak endpoint sandbox dengan kunci produksi — Midtrans menolaknya, jadi pelanggan tak bisa membayar. Samakan: pakai kunci sandbox untuk uji coba, atau set MIDTRANS_IS_PRODUCTION=true saat sudah siap menerima uang sungguhan.');
        } else {
            $this->ok('Midtrans', $isProduction ? 'mode produksi, kunci produksi' : 'mode sandbox, kunci sandbox');
        }
    }

    private function checkStorage(): void
    {
        // file_exists(), bukan is_link()/is_dir(): di Windows symlink yang dibuat
        // Git Bash / storage:link tak dikenali kedua fungsi itu (keduanya false)
        // padahal /storage/... terlayani normal.
        if (! file_exists(public_path('storage'))) {
            $this->bad('public/storage belum ditautkan', 'Jalankan `php artisan storage:link`. Tanpa ini foto mobil, logo tenant, avatar, dan foto struk BBM tidak tampil.');
        } else {
            $this->ok('storage:link terpasang');
        }

        try {
            Storage::disk('public')->put('.preflight', 'ok');
            Storage::disk('public')->delete('.preflight');
            $this->ok('Disk publik bisa ditulis');
        } catch (\Throwable $e) {
            $this->bad('Disk publik tak bisa ditulis', 'Perbaiki izin folder storage/app/public — unggah foto akan gagal. ('.$e->getMessage().')');
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Koneksi database', DB::connection()->getDatabaseName());
        } catch (\Throwable $e) {
            $this->bad('Database tak terhubung', 'Periksa kredensial DB_* di .env. ('.$e->getMessage().')');

            return;
        }

        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
            ->diffKeys(collect(app('migrator')->getRepository()->getRan())->flip())
            ->count();

        if ($pending > 0) {
            $this->bad("{$pending} migration belum dijalankan", 'Jalankan `php artisan migrate --force`.');
        } else {
            $this->ok('Semua migration sudah jalan');
        }
    }

    private function checkBackup(): void
    {
        $files = DatabaseBackup::files();

        if ($files === []) {
            $this->warnAbout('Belum ada backup database', 'Jalankan `php artisan db:backup` sekali untuk memastikan berhasil di server ini, dan pastikan cron terpasang agar berjalan harian.');

            return;
        }

        $latest = $files[0];
        // absolute: diffInHours() Carbon 3 bertanda negatif untuk waktu lampau.
        $age = abs(now()->diffInHours(\Illuminate\Support\Carbon::createFromTimestamp(filemtime($latest))));

        if ($age > 48) {
            $this->warnAbout('Backup terakhir '.round($age).' jam lalu', 'Backup harian tampaknya tak berjalan — periksa cron scheduler.');
        } else {
            $this->ok('Backup terbaru', round($age).' jam lalu');
        }
    }

    private function checkScheduler(): void
    {
        // Tak ada cara portabel memeriksa crontab dari dalam PHP di shared hosting,
        // jadi ini pengingat eksplisit — bukan deteksi otomatis.
        $this->warnAbout(
            'Cron scheduler: pastikan terpasang di server',
            'Pasang cron: `* * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1`. Tanpa ini, penguncian trial kedaluwarsa (tenants:check-trial) TIDAK PERNAH berjalan — pelanggan memakai sistem gratis selamanya — dan backup harian juga tak jalan.'
        );
    }
}
