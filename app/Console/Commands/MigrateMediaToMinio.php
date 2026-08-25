<?php

namespace App\Console\Commands;

use App\Models\Owner;
use App\Models\Setting;
use App\Models\TurfImage;
use App\Services\MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToMinio extends Command
{
    protected $signature = 'media:migrate-minio {--delete-local : Remove files from the public disk after copy}';

    protected $description = 'Copy local turf photos and UPI QRs into ltp-media and rewrite stored keys';

    public function handle(MediaService $media): int
    {
        if (!$media->usingObjectStore()) {
            $this->error('Set FILESYSTEM_MEDIA_DISK=minio and MINIO_* in .env first. Do not reuse AutoWave buckets.');

            return self::FAILURE;
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        $this->info('Turf photos…');
        TurfImage::query()->orderBy('id')->chunkById(50, function ($images) use ($media, &$copied, &$skipped, &$failed) {
            foreach ($images as $image) {
                $result = $this->migrateOne(
                    $media,
                    $image->image_path,
                    $media->turfPhotoStem($image->turf_id, (bool) $image->is_primary),
                    true
                );
                if ($result === null) {
                    $skipped++;
                    continue;
                }
                if ($result === false) {
                    $failed++;
                    continue;
                }
                $image->update(['image_path' => $result]);
                $copied++;
            }
        });

        $this->info('Owner UPI QRs…');
        Owner::query()->whereNotNull('upi_qr_path')->where('upi_qr_path', '!=', '')->orderBy('id')
            ->chunkById(50, function ($owners) use ($media, &$copied, &$skipped, &$failed) {
                foreach ($owners as $owner) {
                    $result = $this->migrateOne($media, $owner->upi_qr_path, $media->ownerQrStem($owner->id), false);
                    if ($result === null) {
                        $skipped++;
                        continue;
                    }
                    if ($result === false) {
                        $failed++;
                        continue;
                    }
                    $owner->update(['upi_qr_path' => $result]);
                    $copied++;
                }
            });

        $platform = Setting::get('platform_qr_path', '');
        if (filled($platform)) {
            $this->info('Platform UPI QR…');
            $result = $this->migrateOne($media, $platform, $media->platformQrStem(), false);
            if ($result === null) {
                $skipped++;
            } elseif ($result === false) {
                $failed++;
            } else {
                Setting::set('platform_qr_path', $result, 'text');
                $copied++;
            }
        }

        $this->info("Copied {$copied}, already on MinIO {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function migrateOne(MediaService $media, ?string $path, string $stem, bool $thumb): string|false|null
    {
        if (!filled($path) || preg_match('#^https?://#i', $path)) {
            return null;
        }

        $path = ltrim($path, '/');
        if ($media->usingObjectStore() && $this->alreadyMigrated($path) && $media->disk()->exists($path)) {
            return null;
        }

        $bytes = $this->readLocal($path);
        if ($bytes === null) {
            $this->warn("Missing local file: {$path}");

            return false;
        }

        try {
            $newKey = $media->putBytes($bytes, strlen($bytes), $stem, $thumb);
        } catch (\Throwable $e) {
            $this->warn("Failed {$path}: {$e->getMessage()}");

            return false;
        }

        if ($this->option('delete-local')) {
            Storage::disk('public')->delete($path);
        }

        return $newKey;
    }

    protected function alreadyMigrated(string $path): bool
    {
        return (bool) preg_match(
            '#^(turfs/\d+/(cover/)?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|owners/\d+/upi-qr|platform/upi-qr)\.(webp|jpg|jpeg|png)$#i',
            $path
        );
    }

    protected function readLocal(string $path): ?string
    {
        if (Storage::disk('public')->exists($path)) {
            return (string) Storage::disk('public')->get($path);
        }

        $absolute = storage_path('app/public/' . $path);
        if (is_readable($absolute)) {
            return (string) file_get_contents($absolute);
        }

        return null;
    }
}
