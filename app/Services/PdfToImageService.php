<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;

class PdfToImageService
{
    /**
     * Convert PDF to JPEG image (all pages stitched together).
     *
     * @param  string  $pdfPath  Path to the PDF file
     * @param  string  $outputPath  Path where the JPEG should be saved
     * @return bool True if conversion was successful
     */
    public function convertToJpeg(string $pdfPath, string $outputPath): bool
    {
        if (! $this->isAvailable()) {
            Log::warning('PDF to image conversion not available - Ghostscript or Imagick not installed');

            return false;
        }

        if (! file_exists($pdfPath)) {
            throw new \Exception('PDF file not found');
        }

        try {
            $pdf = new Pdf($pdfPath);
            $pageCount = $pdf->pageCount();

            // If single page, convert directly
            if ($pageCount === 1) {
                $pdf->setPage(1)
                    ->setOutputFormat('jpg')
                    ->setCompressionQuality(85)
                    ->saveImage($outputPath);

                // Validate single page image
                if (file_exists($outputPath)) {
                    $this->validateImageForGroq($outputPath);

                    return true;
                }

                return false;
            }

            // Convert all pages to individual images
            $tempDir = dirname($outputPath).'/pdf_pages_'.uniqid();
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $pageImages = [];
            for ($page = 1; $page <= $pageCount; $page++) {
                $pagePath = $tempDir.'/page_'.$page.'.jpg';
                $pdf->setPage($page)
                    ->setOutputFormat('jpg')
                    ->setCompressionQuality(85)
                    ->saveImage($pagePath);

                if (file_exists($pagePath)) {
                    $pageImages[] = $pagePath;
                }
            }

            // Stitch all pages into one tall image
            $success = $this->stitchImagesVertically($pageImages, $outputPath);

            // Clean up temporary page images
            foreach ($pageImages as $imagePath) {
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            if (is_dir($tempDir)) {
                rmdir($tempDir);
            }

            // Validate the stitched image doesn't exceed Groq's limits
            if ($success && file_exists($outputPath)) {
                $this->validateImageForGroq($outputPath);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('PDF to image conversion failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Stitch multiple images vertically into one tall image.
     *
     * @param  array<string>  $imagePaths  Paths to images to stitch
     * @param  string  $outputPath  Path where the combined image should be saved
     * @return bool True if stitching was successful
     */
    protected function stitchImagesVertically(array $imagePaths, string $outputPath): bool
    {
        if (empty($imagePaths)) {
            return false;
        }

        // Try using GD first (more common)
        if (extension_loaded('gd')) {
            return $this->stitchWithGd($imagePaths, $outputPath);
        }

        // Fallback to Imagick if available
        if (extension_loaded('imagick')) {
            return $this->stitchWithImagick($imagePaths, $outputPath);
        }

        Log::error('No image library available for stitching (GD or Imagick required)');

        return false;
    }

    /**
     * Stitch images using GD library.
     */
    protected function stitchWithGd(array $imagePaths, string $outputPath): bool
    {
        $images = [];
        $totalHeight = 0;
        $maxWidth = 0;

        // Load all images and calculate dimensions
        foreach ($imagePaths as $path) {
            $img = imagecreatefromjpeg($path);
            if ($img === false) {
                continue;
            }

            $width = imagesx($img);
            $height = imagesy($img);

            $images[] = ['resource' => $img, 'width' => $width, 'height' => $height];
            $totalHeight += $height;
            $maxWidth = max($maxWidth, $width);
        }

        if (empty($images)) {
            return false;
        }

        // Create combined image
        $combined = imagecreatetruecolor($maxWidth, $totalHeight);
        if ($combined === false) {
            return false;
        }

        // Set white background
        $white = imagecolorallocate($combined, 255, 255, 255);
        imagefill($combined, 0, 0, $white);

        // Copy each image into the combined image
        $currentY = 0;
        foreach ($images as $img) {
            imagecopy($combined, $img['resource'], 0, $currentY, 0, 0, $img['width'], $img['height']);
            $currentY += $img['height'];
            imagedestroy($img['resource']);
        }

        // Save combined image
        $success = imagejpeg($combined, $outputPath, 85);
        imagedestroy($combined);

        return $success;
    }

    /**
     * Stitch images using Imagick library.
     */
    protected function stitchWithImagick(array $imagePaths, string $outputPath): bool
    {
        try {
            $combined = new \Imagick;

            foreach ($imagePaths as $path) {
                $img = new \Imagick($path);
                $combined->addImage($img);
                $img->destroy();
            }

            $combined->resetIterator();
            $combined = $combined->appendImages(true); // true = vertical stacking
            $combined->setImageFormat('jpeg');
            $combined->setImageCompressionQuality(85);
            $combined->writeImage($outputPath);
            $combined->destroy();

            return file_exists($outputPath);
        } catch (\Exception $e) {
            Log::error('Imagick stitching failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Validate image meets Groq API requirements.
     *
     * @throws \Exception if image exceeds limits
     */
    protected function validateImageForGroq(string $imagePath): void
    {
        // Check file size (Groq has a 4MB limit for base64 encoded images)
        $fileSize = filesize($imagePath);
        if ($fileSize > 3 * 1024 * 1024) { // 3MB to be safe with base64 overhead
            throw new \Exception('Combined PDF image is too large ('.round($fileSize / 1024 / 1024, 1).'MB). Maximum size is 3MB. Try a PDF with fewer pages or lower resolution.');
        }

        // Check resolution (Groq has a 33 megapixel limit)
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo !== false) {
            [$width, $height] = $imageInfo;
            $megapixels = ($width * $height) / 1000000;

            if ($megapixels > 33) {
                throw new \Exception('Combined PDF image resolution is too high ('.round($megapixels, 1).' megapixels). Maximum is 33 megapixels. Try a PDF with fewer pages or lower resolution.');
            }

            Log::info("PDF converted to image: {$width}x{$height} ({$megapixels}MP, ".round($fileSize / 1024).'KB)');
        }
    }

    /**
     * Check if PDF to image conversion is available.
     */
    public function isAvailable(): bool
    {
        // Check if Ghostscript is available
        exec('which gs 2>/dev/null', $output, $returnCode);
        $gsAvailable = $returnCode === 0;

        // Check if Imagick extension is loaded
        $imagickAvailable = extension_loaded('imagick');

        return $gsAvailable || $imagickAvailable;
    }
}
