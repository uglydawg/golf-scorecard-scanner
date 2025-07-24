<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageProcessingService
{
    public function preprocessImage(string $originalImagePath): string
    {
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $image = Image::read(Storage::disk($storageDisk)->path($originalImagePath));
        
        // Apply image preprocessing steps
        $processedImage = $this->applyPreprocessing($image);
        
        // Save processed image
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $processedPath = str_replace('/originals/', '/processed/', $originalImagePath);
        Storage::disk($storageDisk)->put($processedPath, $processedImage->encode());
        
        return $processedPath;
    }

    private function applyPreprocessing($image)
    {
        $quality = config('scorecard-scanner.processing.image_quality', 85);
        $maxWidth = config('scorecard-scanner.processing.max_width', 2048);
        $maxHeight = config('scorecard-scanner.processing.max_height', 2048);
        
        // 1. Convert to grayscale for better OCR
        $image = $image->greyscale();
        
        // 2. Increase contrast
        $image = $image->contrast(20);
        
        // 3. Apply sharpening
        $image = $image->sharpen(10);
        
        // 4. Resize if too large
        if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
            $image = $image->resize($maxWidth, $maxHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }
        
        return $image;
    }

    public function detectScorecardCorners(string $imagePath): array
    {
        // Mock implementation - would use computer vision to detect corners
        // This would return the four corners of the scorecard for perspective correction
        return [
            'top_left' => ['x' => 50, 'y' => 100],
            'top_right' => ['x' => 800, 'y' => 120],
            'bottom_left' => ['x' => 60, 'y' => 900],
            'bottom_right' => ['x' => 810, 'y' => 920],
        ];
    }

    public function applePerspectiveCorrection(string $imagePath, array $corners): string
    {
        // Mock implementation - would apply perspective correction using the detected corners
        // For now, just return the original path
        return $imagePath;
    }
}