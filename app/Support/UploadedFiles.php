<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class UploadedFiles
{
    public static function first(Request $request, ?string $preferred = null): ?UploadedFile
    {
        if ($preferred && $request->hasFile($preferred)) {
            $file = $request->file($preferred);

            return is_array($file) ? ($file[0] ?? null) : $file;
        }

        foreach (self::all($request) as $file) {
            return $file;
        }

        return null;
    }

    /** @return UploadedFile[] */
    public static function all(Request $request): array
    {
        $files = [];
        foreach ($request->allFiles() as $fileOrArray) {
            foreach (is_array($fileOrArray) ? $fileOrArray : [$fileOrArray] as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }
}
