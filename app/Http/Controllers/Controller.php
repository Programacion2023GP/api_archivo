<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

abstract class Controller
{
    public function imageToBase64(?string $path): ?string
    {
        if (!$path) return null;

        // Si es URL completa, extraer solo la parte del path después de /storage/
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsed   = parse_url($path);
            $urlPath  = $parsed['path'] ?? '';
            // urlPath = "/storage/images/archivo.png"
            // En Laravel, storage/app/public = public_path('storage')
            $relative = ltrim(str_replace('/storage/', '', $urlPath), '/');
            // relative = "images/archivo.png"
        } else {
            $relative = $path;
        }

        $fullPath = Storage::disk('public')->exists($relative)
            ? Storage::disk('public')->path($relative)
            : null;

        if (!$fullPath || !file_exists($fullPath)) return null;

        $mime    = mime_content_type($fullPath);
        $content = file_get_contents($fullPath);

        return "data:{$mime};base64," . base64_encode($content);
    }
    public function ImgUpload($image, $destination, $dir, $imgName)
    {
        // Verificar que la imagen sea v├ílida
        if (!$image || !$image->isValid()) {
            throw new \Exception('La imagen no es v├ílida');
        }

        // Generar nombre ├║nico para el archivo
        $extension = $image->getClientOriginalExtension();
        $filename = $imgName . '_' . time() . '.' . $extension;

        // Subir al microservicio con los par├ímetros espec├¡ficos
        $imageUrl = $this->uploadToMicroservice($image, $destination, $dir, $filename);

        // Devolver la URL completa para la BD
        return $filename;
    }

    /**
     * Funci├│n auxiliar para subir al microservicio con los par├ímetros espec├¡ficos
     */
    private function uploadToMicroservice($file, $destination, $dir, $filename)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false, // Disable SSL verification
            ]);

            $response = $client->request('POST', 'https://api.gpcenter.gomezpalacio.gob.mx/api/smImgUpload', [
                'multipart' => [
                    [
                        'name'     => 'Firma_Director',
                        'contents' => fopen($file->getPathname(), 'r'),
                        'filename' => $filename,
                    ],
                    [
                        'name' => 'dirDestination',
                        'contents' => $destination,
                    ],
                    [
                        'name' => 'dirPath',
                        'contents' => $dir,
                    ],
                    [
                        'name' => 'imgName',
                        'contents' => $filename,
                    ],
                    [
                        'name' => 'requestFileName',
                        'contents' => 'Firma_Director',
                    ],
                ],
                'timeout' => 30, // Add timeout
                'connect_timeout' => 10,
            ]);

            // Check response status
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Error al subir la imagen: ' );
            }

            return $response;
        } catch (\Exception $e) {
            throw new \Exception('Error en uploadToMicroservice: ' );
        }
    }
}
