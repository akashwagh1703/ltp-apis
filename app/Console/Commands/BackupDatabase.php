<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump PostgreSQL to storage/app/backups and prune dumps older than BACKUP_KEEP_DAYS';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('backup:database only supports PostgreSQL.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd-Hi');
        $sqlPath = $dir.DIRECTORY_SEPARATOR."ltp_db-{$stamp}.sql";
        $gzPath = $sqlPath.'.gz';

        $this->info("Dumping {$connection['database']} …");

        $result = Process::env(['PGPASSWORD' => (string) $connection['password']])
            ->timeout(600)
            ->run([
                'pg_dump',
                '-h', (string) $connection['host'],
                '-p', (string) $connection['port'],
                '-U', (string) $connection['username'],
                '-d', (string) $connection['database'],
                '--no-owner',
                '--format=plain',
                '-f', $sqlPath,
            ]);

        if ($result->failed()) {
            $this->error('pg_dump failed: '.$result->errorOutput());
            if (is_file($sqlPath)) {
                File::delete($sqlPath);
            }

            return self::FAILURE;
        }

        try {
            $this->gzipFile($sqlPath, $gzPath);
        } catch (Throwable $e) {
            $this->error('gzip failed: '.$e->getMessage());
            File::delete($sqlPath);

            return self::FAILURE;
        }

        File::delete($sqlPath);

        $bytes = filesize($gzPath) ?: 0;
        $this->info("Wrote {$gzPath} (".number_format($bytes).' bytes)');

        $this->prune($dir);

        return self::SUCCESS;
    }

    protected function gzipFile(string $source, string $dest): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($dest, 'wb9');

        if ($in === false || $out === false) {
            throw new \RuntimeException('Could not open dump files for gzip.');
        }

        while (!feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);
    }

    protected function prune(string $dir): void
    {
        $keepDays = max(1, (int) config('backup.keep_days', 14));
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::files($dir) as $file) {
            if (!str_ends_with(strtolower($file->getFilename()), '.sql.gz')) {
                continue;
            }
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Pruned {$removed} dump(s) older than {$keepDays} days.");
        }
    }
}
