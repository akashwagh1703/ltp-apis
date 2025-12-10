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
            'images' => 'required',
            'images.*' => 'image|max:5120'
        ]);

        $uploadedImages = [];
        
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            $validImages = array_filter($images, function($image) {
                return $image !== null && is_object($image) && method_exists($image, 'isValid') && $image->isValid();
            });
            
            $existingCount = TurfImage::where('turf_id', $turfId)->count();
            $index = 0;
            
            foreach ($validImages as $image) {
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
            }
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
