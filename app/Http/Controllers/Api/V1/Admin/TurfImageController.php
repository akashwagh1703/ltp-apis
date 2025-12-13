<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TurfImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TurfImageController extends Controller
{
    public function upload(Request $request, $turfId)
    {
        \Log::info('Upload request received', [
            'turf_id' => $turfId,
            'has_files' => $request->hasFile('images'),
            'all_files' => $request->allFiles(),
        ]);

        $uploadedImages = [];
        $existingCount = TurfImage::where('turf_id', $turfId)->count();
        
        // Try to get files from different possible keys
        $files = [];
        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        } else {
            // Check all files in request
            $allFiles = $request->allFiles();
            foreach ($allFiles as $key => $file) {
                if (strpos($key, 'image') !== false) {
                    if (is_array($file)) {
                        $files = array_merge($files, $file);
                    } else {
                        $files[] = $file;
                    }
                }
            }
        }

        if (empty($files)) {
            return response()->json(['message' => 'No files received'], 400);
        }

        // Ensure files is an array
        if (!is_array($files)) {
            $files = [$files];
        }

        $index = 0;
        foreach ($files as $file) {
            if (!$file) continue;
            
            \Log::info('Processing file', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'is_valid' => $file->isValid(),
            ]);

            if (!$file->isValid()) {
                \Log::error('Invalid file', ['error' => $file->getError()]);
                continue;
            }

            // Check file type - use both mime type and extension
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());
            
            $allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            $validMime = in_array($mimeType, $allowedMimes);
            $validExtension = in_array($extension, $allowedExtensions);
            
            if (!$validMime && !$validExtension) {
                \Log::error('Invalid file type', [
                    'mime' => $mimeType,
                    'extension' => $extension
                ]);
                continue;
            }

            // Check file size (5MB)
            if ($file->getSize() > 5242880) {
                \Log::error('File too large', ['size' => $file->getSize()]);
                continue;
            }

            try {
                $path = $file->store('turfs', 'public');
                
                if ($path) {
                    $turfImage = TurfImage::create([
                        'turf_id' => $turfId,
                        'image_path' => $path,
                        'is_primary' => $existingCount === 0 && $index === 0,
                        'order' => $existingCount + $index,
                    ]);
                    
                    $uploadedImages[] = $turfImage;
                    $index++;
                    
                    \Log::info('Image uploaded successfully', ['path' => $path]);
                }
            } catch (\Exception $e) {
                \Log::error('Image upload exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if (empty($uploadedImages)) {
            return response()->json([
                'message' => 'No valid images were uploaded. Check file format (JPG, PNG, GIF) and size (max 5MB)'
            ], 400);
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $uploadedImages
        ]);
    }

    public function delete($id)
    {
        $image = TurfImage::findOrFail($id);
        $image->delete();
        
        return response()->json(['message' => 'Image deleted successfully']);
    }

    public function setPrimary($id)
    {
        $image = TurfImage::findOrFail($id);
        
        TurfImage::where('turf_id', $image->turf_id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
        
        return response()->json(['message' => 'Primary image updated']);
    }
}
