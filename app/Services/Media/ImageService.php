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
    public function compressAndResize(string $path, int $maxWidth = 1600, int $quality = 70, bool $forceSquare = false): bool
    {
        $startTime = microtime(true);

        if (!file_exists($path)) {
            return false;
        }

        $originalSize = filesize($path);
        if ($originalSize === false || $originalSize === 0) {
            return false;
        }

        $info = getimagesize($path);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];
        $dimensionsBefore = "{$width}x{$height}";

        // 1. Create source image based on type
        switch ($mime) {
            case 'image/jpeg':
                $source = @imagecreatefromjpeg($path);
                break;
            case 'image/png':
                $source = @imagecreatefrompng($path);
                break;
            case 'image/webp':
                $source = @imagecreatefromwebp($path);
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
                        $rotated = @imagerotate($source, 90, 0);
                        if ($rotated) {
                            imagedestroy($source);
                            $source = $rotated;
                        }
                        break;
                    case 3:
                        $rotated = @imagerotate($source, 180, 0);
                        if ($rotated) {
                            imagedestroy($source);
                            $source = $rotated;
                        }
                        break;
                    case 6:
                        $rotated = @imagerotate($source, -90, 0);
                        if ($rotated) {
                            imagedestroy($source);
                            $source = $rotated;
                        }
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
        $destination = @imagecreatetruecolor($newWidth, $newHeight);
        if (!$destination) {
            imagedestroy($source);
            return false;
        }
        
        // Handle transparency for PNG/WebP (converting to white background for JPEG result)
        if ($mime !== 'image/jpeg') {
            $white = imagecolorallocate($destination, 255, 255, 255);
            imagefill($destination, 0, 0, $white);
        }

        if (!@imagecopyresampled(
            $destination, $source, 
            0, 0, $srcX, $srcY, 
            $newWidth, $newHeight, $srcWidth, $srcHeight
        )) {
            imagedestroy($source);
            imagedestroy($destination);
            return false;
        }

        // 6. Save as optimized JPEG (overwrites original)
        $result = @imagejpeg($destination, $path, $quality);

        // Cleanup
        imagedestroy($source);
        imagedestroy($destination);

        if ($result) {
            clearstatcache(true, $path);
            $optimizedSize = filesize($path);
            $processingTimeMs = round((microtime(true) - $startTime) * 1000, 2);
            $ratio = $originalSize > 0 ? round((1 - ($optimizedSize / $originalSize)) * 100, 2) : 0;
            
            \Illuminate\Support\Facades\Log::info('Image optimized successfully', [
                'path' => $path,
                'original_size_bytes' => $originalSize,
                'optimized_size_bytes' => $optimizedSize,
                'compression_ratio_percent' => $ratio,
                'dimensions_before' => $dimensionsBefore,
                'dimensions_after' => "{$newWidth}x{$newHeight}",
                'processing_time_ms' => $processingTimeMs,
            ]);
        }

        return $result;
    }
}
