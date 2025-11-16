<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;

class PdfToImageService
{
    /**
     * Convert PDF to JPEG image.
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

            // Convert first page only
            $pdf->setPage(1)
                ->setOutputFormat('jpg')
                ->setCompressionQuality(85) // Good quality while keeping file size reasonable
                ->saveImage($outputPath);

            return file_exists($outputPath);
        } catch (\Exception $e) {
            Log::error('PDF to image conversion failed: '.$e->getMessage());

            return false;
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
