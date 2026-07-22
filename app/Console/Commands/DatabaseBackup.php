<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dump seluruh database ke berkas .sql(.gz) di storage/app/backups.
 *
 * Sengaja PHP murni, bukan memanggil `mysqldump`: shared hosting sering tidak
 * menyediakan binary itu maupun shell_exec. Ini harus jalan di mana pun Laravel
 * jalan — backup yang cuma berfungsi di laptop developer sama saja tak ada.
 *
 * Latar: sampai 2026-07-22 proyek ini TIDAK punya backup sama sekali, dan tak
 * satu pun model memakai SoftDeletes. Saat soak menghapus data tenant demo,
 * tak ada apa pun untuk memulihkannya.
 */
class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
        {--keep=14 : Jumlah berkas backup terbaru yang disimpan}
        {--no-compress : Simpan .sql apa adanya, tanpa gzip}';

    protected $description = 'Cadangkan seluruh database ke storage/app/backups';

    public function handle(): int
    {
        $started = microtime(true);
        $name = 'lajur-'.now()->format('Y-m-d_His').'.sql';
        $tmp = self::directory().'/'.$name;

        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }

        $handle = fopen($tmp, 'w');
        if ($handle === false) {
            $this->error('Tidak bisa menulis ke '.dirname($tmp));

            return self::FAILURE;
        }

        $database = DB::connection()->getDatabaseName();

        fwrite($handle, "-- Backup Lajur\n");
        fwrite($handle, '-- Database: '.$database."\n");
        fwrite($handle, '-- Dibuat  : '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );

        $totalRows = 0;

        foreach ($tables as $table) {
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            $ddl = $create['Create Table'] ?? $create['Create View'] ?? null;

            if ($ddl === null) {
                continue; // lewati objek yang tak bisa di-dump (mis. view aneh)
            }

            fwrite($handle, "\n-- ----- {$table} -----\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $ddl.";\n\n");

            $rows = $this->dumpRows($handle, $table);
            $totalRows += $rows;
            $this->line(sprintf('  %-28s %s baris', $table, number_format($rows)));
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $path = $this->option('no-compress') ? $tmp : $this->compress($tmp);
        $size = filesize($path);

        $this->newLine();
        $this->info(sprintf(
            'Backup selesai: %s (%s, %s baris, %.1f detik)',
            basename($path),
            $this->humanSize($size),
            number_format($totalRows),
            microtime(true) - $started
        ));

        $this->prune();

        return self::SUCCESS;
    }

    /** Tulis isi tabel sebagai INSERT, dibaca per potongan agar tabel besar tak menghabiskan memori. */
    private function dumpRows($handle, string $table): int
    {
        $count = 0;

        DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($handle, $table, &$count) {
            $values = [];

            foreach ($rows as $row) {
                $cells = [];
                foreach ((array) $row as $value) {
                    $cells[] = $value === null ? 'NULL' : DB::getPdo()->quote((string) $value);
                }
                $values[] = '('.implode(',', $cells).')';
                $count++;
            }

            if ($values !== []) {
                $columns = implode('`,`', array_keys((array) $rows->first()));
                fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES\n".implode(",\n", $values).";\n");
            }
        });

        return $count;
    }

    private function compress(string $file): string
    {
        if (! function_exists('gzopen')) {
            return $file; // hosting tanpa ekstensi zlib — biarkan .sql apa adanya
        }

        $gz = gzopen($file.'.gz', 'wb9');
        $in = fopen($file, 'rb');

        while (! feof($in)) {
            gzwrite($gz, fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($gz);
        unlink($file);

        return $file.'.gz';
    }

    /** Folder backup. Sengaja path mentah, BUKAN Storage::disk('local'): sejak
     *  Laravel 11 root disk itu `storage/app/private`, sehingga membaca lewat
     *  disk tak menemukan berkas yang ditulis via storage_path('app/backups')
     *  — akibatnya pembersih di bawah tak pernah jalan dan backup menumpuk
     *  sampai disk hosting penuh. */
    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    /** @return list<string> path lengkap, terbaru dahulu */
    public static function files(): array
    {
        $files = glob(self::directory().'/lajur-*.sql*') ?: [];
        rsort($files);

        return $files;
    }

    /** Sisakan N backup terbaru; sisanya dihapus agar disk hosting tak penuh. */
    private function prune(): void
    {
        $keep = max(1, (int) $this->option('keep'));
        $stale = array_slice(self::files(), $keep);

        foreach ($stale as $file) {
            @unlink($file);
        }

        if ($stale !== []) {
            $this->line('  '.count($stale).' backup lama dihapus (menyimpan '.$keep.' terbaru)');
        }
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
