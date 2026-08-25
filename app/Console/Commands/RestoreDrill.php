<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use SplFileInfo;

class RestoreDrill extends Command
{
    protected $signature = 'backup:restore-drill
        {--full : Restore the latest dump into a throwaway database, count turfs, then drop it}';

    protected $description = 'Verify the latest Postgres dump (size + gzip). --full restores into BACKUP_RESTORE_DRILL_DB only — never the live DB.';

    public function handle(): int
    {
        $latest = $this->latestDump();
        if (!$latest) {
            $this->error('No .sql.gz dump found in storage/app/backups.');

            return self::FAILURE;
        }

        $this->info('Latest dump: '.$latest->getPathname());

        if ($latest->getSize() < 100) {
            $this->error('Dump is too small to be a real Postgres backup.');

            return self::FAILURE;
        }

        $header = $this->readGzipPrefix($latest->getPathname(), 2048);
        if ($header === null) {
            $this->error('gzip read failed — file is not a valid .gz archive.');

            return self::FAILURE;
        }

        if (!$this->looksLikePostgresDump($header)) {
            $this->error('Dump header does not look like a PostgreSQL SQL dump.');

            return self::FAILURE;
        }

        $this->info('Verify OK: gzip readable and looks like a PostgreSQL dump ('.number_format($latest->getSize()).' bytes).');

        if (!$this->option('full')) {
            $this->comment('Skip --full unless you have createdb rights on a non-production database.');

            return self::SUCCESS;
        }

        return $this->runFullRestore($latest);
    }

    protected function latestDump(): ?SplFileInfo
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            return null;
        }

        $files = collect(File::files($dir))
            ->filter(fn (SplFileInfo $file) => str_ends_with(strtolower($file->getFilename()), '.sql.gz'))
            ->sortByDesc(fn (SplFileInfo $file) => $file->getMTime())
            ->values();

        return $files->first();
    }

    protected function readGzipPrefix(string $path, int $bytes): ?string
    {
        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $data = gzread($handle, $bytes);
        gzclose($handle);

        return $data === false ? null : $data;
    }

    protected function looksLikePostgresDump(string $header): bool
    {
        return str_contains($header, 'PostgreSQL database dump')
            || str_contains($header, 'SET statement_timeout')
            || str_contains($header, 'SET client_encoding');
    }

    protected function runFullRestore(SplFileInfo $dump): int
    {
        $live = config('database.connections.'.config('database.default'));
        $drillDb = (string) config('backup.restore_drill_db', 'ltp_restore_drill');

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $drillDb)) {
            $this->error('BACKUP_RESTORE_DRILL_DB is not a safe database name.');

            return self::FAILURE;
        }

        if ($drillDb === '' || strcasecmp($drillDb, (string) $live['database']) === 0) {
            $this->error('Refusing --full: BACKUP_RESTORE_DRILL_DB must not be the live database.');

            return self::FAILURE;
        }

        $adminDb = (string) config('backup.restore_admin_db', 'postgres');
        $env = ['PGPASSWORD' => (string) $live['password']];
        $base = [
            '-h', (string) $live['host'],
            '-p', (string) $live['port'],
            '-U', (string) $live['username'],
        ];

        $this->warn("Full drill: restore into {$drillDb}, then DROP DATABASE. Live DB {$live['database']} is not touched.");

        $drop = Process::env($env)->timeout(120)->run(array_merge(
            ['psql'],
            $base,
            ['-d', $adminDb, '-v', 'ON_ERROR_STOP=1', '-c', "DROP DATABASE IF EXISTS {$drillDb};"]
        ));
        if ($drop->failed()) {
            $this->error('Could not drop drill DB: '.$drop->errorOutput());

            return self::FAILURE;
        }

        $create = Process::env($env)->timeout(120)->run(array_merge(
            ['psql'],
            $base,
            ['-d', $adminDb, '-v', 'ON_ERROR_STOP=1', '-c', "CREATE DATABASE {$drillDb};"]
        ));
        if ($create->failed()) {
            $this->error('Could not create drill DB: '.$create->errorOutput());

            return self::FAILURE;
        }

        $sqlPath = storage_path('app/backups/_restore_drill.sql');
        $this->gunzipTo($dump->getPathname(), $sqlPath);

        $restore = Process::env($env)->timeout(1800)->run(array_merge(
            ['psql'],
            $base,
            ['-d', $drillDb, '-v', 'ON_ERROR_STOP=1', '-f', $sqlPath]
        ));
        File::delete($sqlPath);

        if ($restore->failed()) {
            $this->error('Restore into drill DB failed: '.$restore->errorOutput());
            $this->dropDrillDb($env, $base, $adminDb, $drillDb);

            return self::FAILURE;
        }

        $count = Process::env($env)->timeout(60)->run(array_merge(
            ['psql'],
            $base,
            ['-d', $drillDb, '-t', '-A', '-c', 'SELECT count(*) FROM turfs;']
        ));

        if ($count->failed()) {
            $this->error('SELECT count(*) FROM turfs failed: '.$count->errorOutput());
            $this->dropDrillDb($env, $base, $adminDb, $drillDb);

            return self::FAILURE;
        }

        $this->info('turfs count in drill DB: '.trim($count->output()));
        $this->dropDrillDb($env, $base, $adminDb, $drillDb);
        $this->info('Dropped '.$drillDb.'. Restore drill passed.');

        return self::SUCCESS;
    }

    protected function gunzipTo(string $gzPath, string $dest): void
    {
        $in = gzopen($gzPath, 'rb');
        $out = fopen($dest, 'wb');
        if ($in === false || $out === false) {
            throw new \RuntimeException('Could not decompress dump for restore drill.');
        }
        while (!gzeof($in)) {
            $chunk = gzread($in, 1024 * 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);
    }

    protected function dropDrillDb(array $env, array $base, string $adminDb, string $drillDb): void
    {
        Process::env($env)->timeout(120)->run(array_merge(
            ['psql'],
            $base,
            ['-d', $adminDb, '-c', "DROP DATABASE IF EXISTS {$drillDb};"]
        ));
    }
}
