<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 10MB max (in kilobytes)
        ], [
            'file.required' => 'File gambar wajib dipilih.',
            'file.image' => 'File harus berupa gambar yang valid.',
            'file.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        if ($request->file('file')) {
            $path = $request->file('file')->store('uploads', 'public');
            $url = asset('storage/' . $path);
            
            // Ensure HTTPS for production
            if (config('app.env') === 'production') {
                $url = str_replace('http://', 'https://', $url);
            }
            
            return response()->json(['url' => $url]);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }
}
