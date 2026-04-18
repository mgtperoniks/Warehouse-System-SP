<?php

namespace App\Services\Media;

class ImageService
{
    /**
     * Compress and resize an image from an absolute path.
     * 
     * @param string $path Absolute path to the file
     * @param int $maxWidth Max width/height
     * @param int $quality JPEG quality (0-100)
     * @param bool $forceSquare Whether to center-crop to 1:1
     * @return bool
     */
    public function compressAndResize(string $path, int $maxWidth = 1200, int $quality = 75, bool $forceSquare = false): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $info = getimagesize($path);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];

        // 1. Create source image based on type
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($path);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($path);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // 2. Handle Orientation (Exif) for JPEG
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 8:
                        $source = imagerotate($source, 90, 0);
                        break;
                    case 3:
                        $source = imagerotate($source, 180, 0);
                        break;
                    case 6:
                        $source = imagerotate($source, -90, 0);
                        break;
                }
                // Refresh dimensions after rotation
                $width = imagesx($source);
                $height = imagesy($source);
            }
        }

        // 3. Handle Square Cropping (Center Crop)
        $srcX = 0;
        $srcY = 0;
        $srcWidth = $width;
        $srcHeight = $height;

        if ($forceSquare) {
            $size = min($width, $height);
            $srcX = (int)($width - $size) / 2;
            $srcY = (int)($height - $size) / 2;
            $srcWidth = $size;
            $srcHeight = $size;
            
            // Initial dimensions for the crop result
            $width = $size;
            $height = $size;
        }

        // 4. Calculate New Dimensions (Resized)
        $newWidth = $width;
        $newHeight = $height;

        if ($width > $maxWidth || $height > $maxWidth) {
            if ($width > $height) {
                $newWidth = $maxWidth;
                $newHeight = (int)($height * ($maxWidth / $width));
            } else {
                $newHeight = $maxWidth;
                $newWidth = (int)($width * ($maxWidth / $height));
            }
        }

        // 5. Resample
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Handle transparency for PNG/WebP (converting to white background for JPEG result)
        if ($mime !== 'image/jpeg') {
            $white = imagecolorallocate($destination, 255, 255, 255);
            imagefill($destination, 0, 0, $white);
        }

        imagecopyresampled(
            $destination, $source, 
            0, 0, $srcX, $srcY, 
            $newWidth, $newHeight, $srcWidth, $srcHeight
        );

        // 6. Save as optimized JPEG (overwrites original)
        $result = imagejpeg($destination, $path, $quality);

        // Cleanup
        imagedestroy($source);
        imagedestroy($destination);

        return $result;
    }
}
