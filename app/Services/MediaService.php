<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaService
{
    public const MAX_BYTES = 12582912; // 12MB
    public const PRESIGN_MINUTES = 10;

    public function diskName(): string
    {
        $configured = (string) config('filesystems.media_disk', 'public');
        if ($configured === 'minio'
            && filled(config('filesystems.disks.minio.key'))
            && filled(config('filesystems.disks.minio.secret'))
            && filled(config('filesystems.disks.minio.endpoint'))
        ) {
            return 'minio';
        }
        if ($configured === 's3'
            && filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.bucket'))
        ) {
            return 's3';
        }

        return 'public';
    }

    public function usingObjectStore(): bool
    {
        return in_array($this->diskName(), ['minio', 's3'], true);
    }

    public function disk()
    {
        return Storage::disk($this->diskName());
    }

    public function turfPhotoStem(int $turfId, bool $cover): string
    {
        $uuid = (string) Str::uuid();

        return $cover
            ? $this->keyPrefix() . "turfs/{$turfId}/cover/{$uuid}"
            : $this->keyPrefix() . "turfs/{$turfId}/{$uuid}";
    }

    public function ownerQrStem(int $ownerId): string
    {
        return $this->keyPrefix() . "owners/{$ownerId}/upi-qr";
    }

    public function platformQrStem(): string
    {
        return $this->keyPrefix() . 'platform/upi-qr';
    }

    public function tmpStem(string $actor, int $actorId): string
    {
        return $this->keyPrefix() . "tmp/{$actor}/{$actorId}/" . Str::uuid();
    }

    public function thumbKey(string $key): string
    {
        return preg_replace('/(\.[a-z0-9]+)$/i', '-thumb$1', $key) ?: $key;
    }

    public function putUploadedFile(UploadedFile $file, string $stem, bool $thumb = true): string
    {
        $bytes = $this->readUploadedBytes($file);

        return $this->putBytes($bytes, strlen($bytes), $stem, $thumb);
    }

    protected function readUploadedBytes(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new RuntimeException('The photo did not upload completely. Try a smaller JPG or PNG.');
        }

        try {
            $bytes = $file->getContent();
        } catch (\Throwable) {
            $bytes = false;
        }

        if ($bytes === false || $bytes === '') {
            $path = $file->getPathname() ?: $file->getRealPath();
            if ($path && is_readable($path)) {
                $bytes = (string) file_get_contents($path);
            }
        }

        if (!is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Could not read the uploaded photo. Try another JPG or PNG.');
        }

        return $bytes;
    }

    public function putBytes(string $bytes, int $reportedSize, string $stem, bool $thumb = true): string
    {
        $size = $reportedSize > 0 ? $reportedSize : strlen($bytes);
        $this->assertSafeImage($bytes, $size);
        [$body, $ext] = $this->encode($bytes, 1600);
        $key = $stem . '.' . $ext;
        $this->write($key, $body);

        if ($thumb) {
            [$thumbBody, $thumbExt] = $this->encode($bytes, 480);
            $this->write($this->thumbKey($stem . '.' . $thumbExt), $thumbBody);
        }

        return $key;
    }

    public function completeTmp(string $tmpKey, string $stem, bool $thumb = true): string
    {
        if (!$this->disk()->exists($tmpKey)) {
            throw new RuntimeException('Upload not found. Try again.');
        }

        $bytes = (string) $this->disk()->get($tmpKey);
        $final = $this->putBytes($bytes, strlen($bytes), $stem, $thumb);
        $this->disk()->delete($tmpKey);

        return $final;
    }

    public function delete(?string $key): void
    {
        if (!filled($key) || str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return;
        }

        $key = ltrim($key, '/');
        foreach ([$this->diskName(), 'public'] as $disk) {
            try {
                Storage::disk($disk)->delete($key);
                Storage::disk($disk)->delete($this->thumbKey($key));
            } catch (\Throwable) {
                // ignore missing disks/objects
            }
        }
    }

    public function url(?string $key): ?string
    {
        if (!filled($key)) {
            return null;
        }

        if (preg_match('#^https?://#i', $key)) {
            return $key;
        }

        $key = ltrim($key, '/');

        if ($this->usingObjectStore() && $this->isPublicObjectKey($key)) {
            return $this->publicUrl($key);
        }

        return rtrim((string) config('app.url'), '/') . '/storage/' . $key;
    }

    public function thumbUrl(?string $key): ?string
    {
        if (!filled($key) || preg_match('#^https?://#i', $key)) {
            return $this->url($key);
        }

        return $this->url($this->thumbKey(ltrim($key, '/')));
    }

    public function temporaryPrivateUrl(string $key, int $minutes = self::PRESIGN_MINUTES): string
    {
        return Storage::disk('minio_private')->temporaryUrl($key, now()->addMinutes($minutes));
    }

    public function presignPut(string $tmpKey, string $contentType): array
    {
        if (!$this->usingObjectStore()) {
            throw new RuntimeException('Object storage is not configured.');
        }

        $expires = now()->addMinutes(self::PRESIGN_MINUTES);
        $result = $this->disk()->temporaryUploadUrl($tmpKey, $expires, [
            'ContentType' => $contentType,
        ]);

        return [
            'key' => $tmpKey,
            'upload_url' => $result['url'],
            'headers' => $result['headers'] ?? ['Content-Type' => $contentType],
            'expires_in' => self::PRESIGN_MINUTES * 60,
            'max_bytes' => self::MAX_BYTES,
        ];
    }

    public function assertTmpKey(string $key, string $actor, int $actorId): void
    {
        $prefix = $this->keyPrefix() . "tmp/{$actor}/{$actorId}/";
        if (!str_starts_with($key, $prefix) || str_contains($key, '..')) {
            throw new RuntimeException('Invalid upload key.');
        }
    }

    protected function keyPrefix(): string
    {
        $prefix = trim((string) config('filesystems.media_prefix', 'ltp'), '/');

        return $prefix === '' ? '' : $prefix . '/';
    }

    protected function write(string $key, string $body): void
    {
        $this->ensureBucket();
        $disk = $this->disk();

        try {
            $ok = $disk->put($key, $body);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'NoSuchBucket')) {
                $this->bucketReady = false;
                $this->ensureBucket();
                $ok = $disk->put($key, $body);
            } else {
                if ($this->objectLooksStored($disk, $key)) {
                    \Log::warning('Media object saved; skipping ACL/visibility', [
                        'disk' => $this->diskName(),
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);

                    return;
                }

                \Log::error('Media write failed', [
                    'disk' => $this->diskName(),
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException($this->writeErrorMessage($e->getMessage()));
            }
        }

        if ($ok === false) {
            if ($this->objectLooksStored($disk, $key)) {
                return;
            }

            \Log::error('Media write returned false', [
                'disk' => $this->diskName(),
                'key' => $key,
            ]);
            throw new RuntimeException('Could not store the photo. Check MinIO endpoint, bucket, and keys.');
        }
    }

    protected bool $bucketReady = false;

    protected function ensureBucket(): void
    {
        if ($this->bucketReady || !$this->usingObjectStore()) {
            return;
        }

        $disk = $this->disk();
        if (!method_exists($disk, 'getClient')) {
            $this->bucketReady = true;

            return;
        }

        $client = $disk->getClient();
        $bucket = (string) config('filesystems.disks.' . $this->diskName() . '.bucket');
        if ($bucket === '') {
            throw new RuntimeException('MINIO_BUCKET is empty. Set it to playltp.');
        }

        try {
            if (!$client->doesBucketExist($bucket)) {
                $params = ['Bucket' => $bucket];
                $region = (string) config('filesystems.disks.minio.region', 'us-east-1');
                if ($region !== '' && $region !== 'us-east-1') {
                    $params['CreateBucketConfiguration'] = ['LocationConstraint' => $region];
                }
                $client->createBucket($params);
                \Log::info('Created MinIO bucket', ['bucket' => $bucket]);
            }
            $this->ensurePublicRead($client, $bucket);
        } catch (\Throwable $e) {
            \Log::error('MinIO ensure bucket failed', [
                'bucket' => $bucket,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException($this->writeErrorMessage($e->getMessage()));
        }

        $this->bucketReady = true;
    }

    protected function ensurePublicRead($client, string $bucket): void
    {
        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Sid' => 'LtpPublicRead',
                'Effect' => 'Allow',
                'Principal' => ['AWS' => ['*']],
                'Action' => ['s3:GetObject'],
                'Resource' => ['arn:aws:s3:::' . $bucket . '/*'],
            ]],
        ], JSON_UNESCAPED_SLASHES);

        try {
            $client->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => $policy,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Could not set MinIO public-read policy', ['error' => $e->getMessage()]);
        }
    }

    protected function objectLooksStored($disk, string $key): bool
    {
        try {
            return $disk->exists($key);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function writeErrorMessage(string $msg): string
    {
        if (str_contains($msg, 'Failed to connect') || str_contains($msg, 'timed out') || str_contains($msg, 'Connection refused') || str_contains($msg, 'cURL error')) {
            return 'Could not reach MinIO. On the API server use MINIO_ENDPOINT=http://127.0.0.1:9000';
        }
        if (str_contains($msg, 'AccessDenied') || str_contains($msg, 'InvalidAccessKeyId') || str_contains($msg, 'SignatureDoesNotMatch') || str_contains($msg, '403')) {
            return 'MinIO rejected the upload. Check MINIO_ACCESS_KEY, MINIO_SECRET_KEY, and bucket playltp.';
        }
        if (str_contains($msg, 'NoSuchBucket') || str_contains($msg, 'NotFound')) {
            return 'MinIO bucket playltp was missing. The API will create it on the next upload after you deploy, or run: mc mb ltp/playltp';
        }
        if (str_contains($msg, 'UnableToSetVisibility') || str_contains($msg, 'AccessControlList') || str_contains($msg, 'PutObjectAcl')) {
            return 'MinIO does not allow public ACL. Uploads now skip ACL; redeploy this API.';
        }

        return 'Could not store the photo. Check API storage logs.';
    }

    protected function publicUrl(string $key): string
    {
        $base = rtrim((string) config('filesystems.media_public_url'), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/') . '/api/v1/public/media';
        }

        return $base . '/' . ltrim($key, '/');
    }

    public function isPublicObjectKey(string $key): bool
    {
        return $this->isObjectKey($key);
    }

    protected function isObjectKey(string $key): bool
    {
        $prefix = trim((string) config('filesystems.media_prefix', 'ltp'), '/');
        $tmp = ($prefix === '' ? 'tmp/' : $prefix . '/tmp/');
        if (str_starts_with($key, $tmp) || str_starts_with($key, 'tmp/')) {
            return false;
        }

        $canonical = preg_replace('/-thumb(\.[a-z0-9]+)$/i', '$1', $key);
        $optionalPrefix = $prefix === '' ? '' : '(?:' . preg_quote($prefix, '#') . '/)?';

        return (bool) preg_match(
            '#^' . $optionalPrefix . '(turfs/\d+/(cover/)?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|owners/\d+/upi-qr|platform/upi-qr)\.(webp|jpg|jpeg|png)$#i',
            $canonical
        );
    }

    protected function assertSafeImage(string $bytes, int $size): void
    {
        if ($size > self::MAX_BYTES || strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('Image must be 12MB or smaller.');
        }

        $mime = $this->mimeFromMagic($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException('Only JPG, PNG, GIF, or WebP images are allowed.');
        }
    }

    protected function mimeFromMagic(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($bytes) >= 12 && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    protected function encode(string $bytes, int $maxEdge): array
    {
        if (!function_exists('imagecreatefromstring')) {
            $mime = $this->mimeFromMagic($bytes);
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            return [$bytes, $ext];
        }

        $src = @imagecreatefromstring($bytes);
        if (!$src) {
            throw new RuntimeException('That file is not a valid image.');
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $scale = min(1, $maxEdge / max($width, $height, 1));
        if ($scale < 1) {
            $newW = max(1, (int) round($width * $scale));
            $newH = max(1, (int) round($height * $scale));
            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($src);
            $src = $dst;
        }

        imagealphablending($src, true);
        imagesavealpha($src, true);

        ob_start();
        $webpOk = function_exists('imagewebp') && imagewebp($src, null, 82);
        $out = (string) ob_get_clean();
        if ($webpOk && $out !== '') {
            imagedestroy($src);

            return [$out, 'webp'];
        }

        ob_start();
        imagejpeg($src, null, 85);
        $jpg = (string) ob_get_clean();
        imagedestroy($src);

        return [$jpg, 'jpg'];
    }
}
