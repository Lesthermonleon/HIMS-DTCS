<?php

namespace App\Http\Controllers;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class BrandingController extends Controller
{
    /**
     * Serve the hospital logo from private storage.
     */
    public function logo(SettingsService $settings): Response
    {
        $logoPath = $settings->get('hospital.logo_path');

        if ($logoPath) {
            $fullPath = storage_path('app/' . $logoPath);
            if (File::exists($fullPath)) {
                $mimeType = File::mimeType($fullPath);
                $fileContent = File::get($fullPath);

                return response($fileContent, 200, [
                    'Content-Type'  => $mimeType,
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        // Return 1x1 transparent PNG if no logo configured
        $transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return response($transparentPng, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
