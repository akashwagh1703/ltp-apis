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
        try {
            // Get all files from request
            $allFiles = $request->allFiles();
            
            if (empty($allFiles)) {
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

                    // Store the file
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
                    } else {
                        $errors[] = $file->getClientOriginalName() . ': Storage failed';
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
                'images' => $uploadedImages,
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
