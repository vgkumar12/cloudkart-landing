<?php

namespace App\Helpers;

/**
 * ImageHelper - Handles image processing tasks
 */
class ImageHelper {
    
    /**
     * Generate a thumbnail from a source image
     * 
     * @param string $sourcePath Full path to source image
     * @param string $targetPath Full path where thumbnail should be saved
     * @param int $maxWidth Maximum width of the thumbnail
     * @param int $maxHeight Maximum height of the thumbnail
     * @param int $quality JPEG/WebP quality (0-100)
     * @return bool Success or failure
     */
    public static function generateThumbnail(string $sourcePath, string $targetPath, int $maxWidth = 300, int $maxHeight = 300, int $quality = 80): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Get image dimensions and type
        list($origWidth, $origHeight, $imageType) = getimagesize($sourcePath);

        // Calculate new dimensions while maintaining aspect ratio
        $ratio = $origWidth / $origHeight;
        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = $maxHeight * $ratio;
            $newHeight = $maxHeight;
        } else {
            $newHeight = $maxWidth / $ratio;
            $newWidth = $maxWidth;
        }

        // Create new image container
        $thumb = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

        // Load source image based on type
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                // Handle PNG transparency
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Resize
        imagecopyresampled(
            $thumb, 
            $source, 
            0, 0, 0, 0, 
            (int)$newWidth, (int)$newHeight, 
            $origWidth, $origHeight
        );

        // Ensure target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Save thumbnail based on original type
        $success = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($thumb, $targetPath, $quality);
                break;
            case IMAGETYPE_PNG:
                // PNG quality is 0-9
                $pngQuality = (int)round((100 - $quality) / 10);
                $success = imagepng($thumb, $targetPath, max(0, min(9, $pngQuality)));
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($thumb, $targetPath);
                break;
            case IMAGETYPE_WEBP:
                $success = imagewebp($thumb, $targetPath, $quality);
                break;
        }

        // Clean up
        imagedestroy($thumb);
        imagedestroy($source);

        return $success;
    }

    /**
     * Get thumbnail path from original image path
     * e.g. "uploads/products/image.jpg" -> "uploads/products/thumbs/image.jpg"
     * 
     * @param string $imagePath Original image path
     * @return string Thumbnail path
     */
    public static function getThumbPath(string $imagePath): string {
        $dir = dirname($imagePath);
        $file = basename($imagePath);
        return $dir . '/thumbs/' . $file;
    }
}
