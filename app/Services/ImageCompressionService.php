<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Tinify\Tinify;

class ImageCompressionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        Tinify::setKey(config('services.tinypng.api_key'));
    }

    /**
     * Compress an image file using TinyPNG.
     *
     * @param  string  $sourcePath  Path to the source image file
     * @param  string  $destinationPath  Path where the compressed image should be saved
     * @return bool Returns true if compression was successful
     *
     * @throws \Exception
     */
    public function compress(string $sourcePath, string $destinationPath): bool
    {
        if (! file_exists($sourcePath)) {
            throw new \Exception('Source image file not found');
        }

        if (! config('services.tinypng.api_key')) {
            Log::warning('TinyPNG API key not configured. Skipping compression.');

            // Just copy the file without compression if API key is not set
            return copy($sourcePath, $destinationPath);
        }

        try {
            $source = Tinify::fromFile($sourcePath);
            $source->toFile($destinationPath);

            Log::info('Image compressed successfully', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'compressions_this_month' => Tinify::compressionCount(),
            ]);

            return true;
        } catch (\Tinify\AccountException $e) {
            Log::error('TinyPNG Account Error: '.$e->getMessage());

            throw new \Exception('Image compression failed: Invalid TinyPNG API key or limit reached');
        } catch (\Tinify\ClientException $e) {
            Log::error('TinyPNG Client Error: '.$e->getMessage());

            throw new \Exception('Image compression failed: Invalid request');
        } catch (\Tinify\ServerException $e) {
            Log::error('TinyPNG Server Error: '.$e->getMessage());

            throw new \Exception('Image compression failed: TinyPNG server error');
        } catch (\Tinify\ConnectionException $e) {
            Log::error('TinyPNG Connection Error: '.$e->getMessage());

            throw new \Exception('Image compression failed: Network connection error');
        } catch (\Exception $e) {
            Log::error('TinyPNG Error: '.$e->getMessage());

            throw new \Exception('Image compression failed: '.$e->getMessage());
        }
    }

    /**
     * Get the number of compressions used this month.
     */
    public function getCompressionCount(): ?int
    {
        try {
            return Tinify::compressionCount();
        } catch (\Exception $e) {
            return null;
        }
    }
}
