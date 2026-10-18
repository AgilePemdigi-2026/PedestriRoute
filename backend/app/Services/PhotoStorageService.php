<?php

namespace App\Services;

use App\Support\MessageCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoStorageService
{
    /**
     * Validate and process uploaded report photo.
     * Checks magic bytes, strips EXIF, resizes to max 1600px, re-encodes to JPEG.
     */
    public function processAndStore(UploadedFile $file): string
    {
        // 1. Validate file size (max CFG_PHOTO_MAX_MB)
        $maxMb = (int) env('CFG_PHOTO_MAX_MB', 5);
        if ($file->getSize() > ($maxMb * 1024 * 1024)) {
            throw new \InvalidArgumentException(MessageCatalog::REPORT_PHOTO_INVALID);
        }

        // 2. Validate magic bytes (actual mime type)
        $allowedMimes = ['image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if (!in_array($realMime, $allowedMimes, true)) {
            throw new \InvalidArgumentException(MessageCatalog::REPORT_PHOTO_INVALID);
        }

        // 3. Load image using GD
        $image = null;
        if ($realMime === 'image/jpeg') {
            $image = @imagecreatefromjpeg($file->getRealPath());
        } elseif ($realMime === 'image/png') {
            $image = @imagecreatefrompng($file->getRealPath());
        }

        if (!$image) {
            throw new \InvalidArgumentException(MessageCatalog::REPORT_PHOTO_INVALID);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxPx = (int) env('CFG_PHOTO_MAX_PX', 1600);

        // Check resize necessity
        if ($width > $maxPx || $height > $maxPx) {
            if ($width >= $height) {
                $newWidth = $maxPx;
                $newHeight = (int) round(($height / $width) * $maxPx);
            } else {
                $newHeight = $maxPx;
                $newWidth = (int) round(($width / $height) * $maxPx);
            }

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }

        // 4. Output to JPEG buffer (EXIF metadata is naturally stripped)
        ob_start();
        imagejpeg($image, null, 85);
        $jpegData = ob_get_clean();
        imagedestroy($image);

        // 5. Save to storage
        $filename = 'reports/' . Str::uuid()->toString() . '.jpg';
        Storage::disk('public')->put($filename, $jpegData);

        return $filename;
    }

    /**
     * Delete photo from storage if transaction fails.
     */
    public function delete(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
