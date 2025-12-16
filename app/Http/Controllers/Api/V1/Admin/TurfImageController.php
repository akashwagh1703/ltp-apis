<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TurfImageResource;
use App\Models\TurfImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TurfImageController extends Controller
{
    public function upload(Request $request, $turfId)
    {
        try {
            \Log::info('Image upload started', [
                'turf_id' => $turfId,
                'has_files' => $request->hasFile('images'),
                'all_files' => array_keys($request->allFiles()),
            ]);

            // Get all files from request
            $allFiles = $request->allFiles();
            
            if (empty($allFiles)) {
                \Log::error('No files received');
                return response()->json([
                    'message' => 'No files received',
                    'debug' => [
                        'content_type' => $request->header('Content-Type'),
                        'method' => $request->method(),
                    ]
                ], 400);
            }

            $uploadedImages = [];
            $existingCount = TurfImage::where('turf_id', $turfId)->count();
            $index = 0;
            $errors = [];

            // Process all files
            foreach ($allFiles as $key => $fileOrArray) {
                $files = is_array($fileOrArray) ? $fileOrArray : [$fileOrArray];
                
                foreach ($files as $file) {
                    if (!$file || !$file->isValid()) {
                        $errors[] = 'Invalid file: ' . ($file ? $file->getClientOriginalName() : 'unknown');
                        continue;
                    }

                    // Just check extension, ignore mime type issues
                    $extension = strtolower($file->getClientOriginalExtension());
                    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $errors[] = $file->getClientOriginalName() . ': Invalid extension';
                        continue;
                    }

                    // Store the file manually
                    try {
                        $originalName = $file->getClientOriginalName();
                        $sanitizedName = str_replace(' ', '_', $originalName);
                        $filename = time() . '_' . $index . '_' . $sanitizedName;
                        $path = $file->storeAs('turfs', $filename, 'public');
                        
                        \Log::info('File stored', [
                            'path' => $path,
                            'turf_id' => $turfId,
                            'filename' => $filename,
                            'original' => $file->getClientOriginalName()
                        ]);
                        
                        if (!$path || empty($path)) {
                            \Log::error('Store returned empty path', ['file' => $file->getClientOriginalName()]);
                            $errors[] = $file->getClientOriginalName() . ': Storage returned empty path';
                            continue;
                        }
                        
                        $turfImage = TurfImage::create([
                            'turf_id' => $turfId,
                            'image_path' => $path,
                            'is_primary' => $existingCount === 0 && $index === 0,
                            'order' => $existingCount + $index,
                        ]);
                        
                        \Log::info('Image record created', ['id' => $turfImage->id, 'path' => $path]);
                        
                        $uploadedImages[] = $turfImage;
                        $index++;
                        
                    } catch (\Exception $e) {
                        \Log::error('Storage exception: ' . $e->getMessage(), [
                            'file' => $file->getClientOriginalName(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
                        continue;
                    }
                }
            }

            if (empty($uploadedImages)) {
                return response()->json([
                    'message' => 'No valid images uploaded',
                    'errors' => $errors,
                    'files_received' => count($allFiles)
                ], 400);
            }

            return response()->json([
                'message' => 'Images uploaded successfully',
                'images' => TurfImageResource::collection($uploadedImages),
                'count' => count($uploadedImages)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
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
