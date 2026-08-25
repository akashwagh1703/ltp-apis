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
            ? "turfs/{$turfId}/cover/{$uuid}"
            : "turfs/{$turfId}/{$uuid}";
    }

    public function ownerQrStem(int $ownerId): string
    {
        return "owners/{$ownerId}/upi-qr";
    }

    public function platformQrStem(): string
    {
        return 'platform/upi-qr';
    }

    public function tmpStem(string $actor, int $actorId): string
    {
        return "tmp/{$actor}/{$actorId}/" . Str::uuid();
    }

    public function thumbKey(string $key): string
    {
        return preg_replace('/(\.[a-z0-9]+)$/i', '-thumb$1', $key) ?: $key;
    }

    public function putUploadedFile(UploadedFile $file, string $stem, bool $thumb = true): string
    {
        $path = $file->getRealPath();
        if (!$path || !is_readable($path)) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        return $this->putBytes((string) file_get_contents($path), $file->getSize() ?: 0, $stem, $thumb);
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

        if ($this->usingObjectStore() && $this->isObjectKey($key)) {
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
        $prefix = "tmp/{$actor}/{$actorId}/";
        if (!str_starts_with($key, $prefix) || str_contains($key, '..')) {
            throw new RuntimeException('Invalid upload key.');
        }
    }

    protected function write(string $key, string $body): void
    {
        $ok = $this->disk()->put($key, $body);
        if (!$ok) {
            throw new RuntimeException('Could not store the file.');
        }
    }

    protected function publicUrl(string $key): string
    {
        $base = rtrim((string) config('filesystems.disks.' . $this->diskName() . '.url'), '/');
        if ($base === '') {
            $endpoint = rtrim((string) config('filesystems.disks.' . $this->diskName() . '.endpoint'), '/');
            $bucket = (string) config('filesystems.disks.' . $this->diskName() . '.bucket');
            $base = $endpoint . '/' . $bucket;
        }

        return $base . '/' . ltrim($key, '/');
    }

    protected function isObjectKey(string $key): bool
    {
        if (str_starts_with($key, 'tmp/')) {
            return false;
        }

        $canonical = preg_replace('/-thumb(\.[a-z0-9]+)$/i', '$1', $key);

        return (bool) preg_match(
            '#^(turfs/\d+/(cover/)?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|owners/\d+/upi-qr|platform/upi-qr)\.(webp|jpg|jpeg|png)$#i',
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
