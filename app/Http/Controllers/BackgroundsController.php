<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Validator;

class BackgroundsController extends Controller
{
    public function list()
    {
        //find all the files in the public/backgrounds folder
        $files = Storage::disk('public')->files('backgrounds');

        //keep only the files that are images
        $files = array_filter($files, function ($file) {
            return in_array(
                strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                ['jpg', 'jpeg', 'png', 'gif', 'webp']
            );
        });

        $files = array_map(function ($file) {
            return rawurlencode(str_replace(['backgrounds/', '/backgrounds/'], '', $file));
        }, $files);

        $files = array_values($files);

        return response()->json([
            'status' => 'success',
            'message' => 'Background images listed successfully',
            'data' => [
                'files' => $files,
            ]
        ]);
    }

    public function upload(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'background_image' => 'required|image|mimes:jpg,jpeg,png,gif,webp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image upload failed',
                'data' => [
                    'errors' => $validator->errors(),
                ]
            ], 422);
        }

        try {
            $file = $request->file('background_image');
            $fileName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $safeFilename = basename($fileName);
            $file->storeAs('backgrounds', $safeFilename, 'public');

            return response()->json([
                'status' => 'success',
                'message' => 'Background image uploaded successfully',
                'data' => [
                    'file' => $safeFilename . '.' . $extension,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image upload failed',
            ], 500);
        }
    }

    public function delete($file)
    {
        //check if the file exists
        if (!Storage::disk('public')->exists('backgrounds/' . $file)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image not found',
            ], 404);
        }
        //delete the file
        try {
            //delete the image itsself
            Storage::disk('public')->delete('backgrounds/' . $file);
            //delete the cached image
            Storage::disk('public')->delete('backgrounds/cache/' . $file);
            //delete the cached thumbs
            Storage::disk('public')->delete('backgrounds/cache/thumbs/' . $file);

            return response()->json([
                'status' => 'success',
                'message' => 'Background image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image deletion failed',
            ], 500);
        }
    }

    public function use($file)
    {

        //do we have a cached version of the image?
        $cachedPath = Storage::disk('public')->path('backgrounds/cache/' . $file);
        if (file_exists($cachedPath)) {
            return response()->file($cachedPath);
        }

        $fullPath = Storage::disk('public')->path('backgrounds/' . $file);
        //check the file exists
        if (!file_exists($fullPath)) {
            abort(404);
        }
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);

        $image->scale(width: 2000);
        $encoded = $image->toJpeg(95);

        //save the encoded image to the public/backgrounds/cache folder
        Storage::disk('public')->put('backgrounds/cache/' . $file, $encoded);

        return response($encoded)->header('Content-Type', 'image/webp');
    }

    public function useThumb($file)
    {

        //do we have a cached version of the image?
        $cachedPath = Storage::disk('public')->path('backgrounds/cache/thumbs/' . $file);
        if (file_exists($cachedPath)) {
            return response()->file($cachedPath);
        }

        $fullPath = Storage::disk('public')->path('backgrounds/' . $file);
        if (!file_exists($fullPath)) {
            abort(404);
        }
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);
        $image->scale(width: 100);
        $encoded = $image->toWebp(80);

        //save the encoded image to the public/backgrounds/cache folder
        Storage::disk('public')->put('backgrounds/cache/thumbs/' . $file, $encoded);

        return response($encoded)->header('Content-Type', 'image/webp');
    }
}
