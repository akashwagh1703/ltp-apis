<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TurfImage;
use Illuminate\Http\Request;

class TurfImageController extends Controller
{
    public function upload(Request $request, $turfId)
    {
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'file|image|mimes:jpeg,jpg,png,gif|max:5120'
        ], [
            'images.required' => 'Please select at least one image',
            'images.*.mimes' => 'Only JPG, JPEG, PNG, and GIF images are allowed',
            'images.*.max' => 'Image size must not exceed 5MB',
        ]);

        $uploadedImages = [];
        
        if ($request->hasFile('images')) {
            $images = is_array($request->file('images')) ? $request->file('images') : [$request->file('images')];
            $existingCount = TurfImage::where('turf_id', $turfId)->count();
            $index = 0;
            
            foreach ($images as $image) {
                if ($image && $image->isValid()) {
                    try {
                        $path = $image->store('turfs', 'public');
                        if ($path) {
                            $turfImage = TurfImage::create([
                                'turf_id' => $turfId,
                                'image_path' => $path,
                                'is_primary' => $existingCount === 0 && $index === 0,
                                'order' => $existingCount + $index,
                            ]);
                            $uploadedImages[] = $turfImage;
                            $index++;
                        }
                    } catch (\Exception $e) {
                        \Log::error('Image upload failed: ' . $e->getMessage());
                    }
                }
            }
        }

        if (empty($uploadedImages)) {
            return response()->json(['message' => 'No images were uploaded'], 400);
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
