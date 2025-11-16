<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GroqService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        protected Client $client,
        protected string $apiKey = '',
    ) {
        $this->apiKey = config('services.groq.api_key', '');
    }

    /**
     * Analyze a receipt image and extract expense information.
     *
     * @param  string  $imagePath  Path to the receipt image
     * @return array{amount: float, currency: string, description: string, category: string}
     */
    public function analyzeReceipt(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            throw new \Exception('Image file not found');
        }

        // Detect mime type, with fallback based on file extension
        $mimeType = mime_content_type($imagePath);

        if (! $mimeType || ! str_starts_with($mimeType, 'image/')) {
            // Fallback: determine mime type from file extension
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => throw new \Exception('Unsupported image format. Please use JPEG, PNG, or WebP.')
            };
        }

        // Check file size (Groq has a 4MB limit for base64 encoded images)
        $fileSize = filesize($imagePath);
        if ($fileSize > 3 * 1024 * 1024) { // 3MB to be safe with base64 overhead
            throw new \Exception('Image file is too large. Maximum size is 3MB. Please use a smaller image.');
        }

        $fileContents = file_get_contents($imagePath);
        if ($fileContents === false) {
            throw new \Exception('Failed to read image file');
        }

        $imageData = base64_encode($fileContents);

        if (empty($imageData)) {
            throw new \Exception('Failed to encode image as base64');
        }

        // Construct the data URL
        $dataUrl = "data:{$mimeType};base64,{$imageData}";

        try {
            $response = $this->client->post('https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Analyze this receipt image and extract the following information in JSON format: total amount (as a number), currency (the 3-letter ISO code: GBP, EUR, CZK, or USD - look for £, €, Kč, $ symbols or context clues), description (what was purchased - be brief, max 100 characters), and category (choose one from: Food, Transportation, Shopping, Utilities, Entertainment, Healthcare, Other). Return ONLY valid JSON with keys: amount, currency, description, category. Do not include any markdown formatting or code blocks.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $dataUrl,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 300,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $content = $result['choices'][0]['message']['content'] ?? '';

            // Remove markdown code blocks if present
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);

            $extracted = json_decode($content, true);

            if (! $extracted || ! isset($extracted['amount'])) {
                throw new \Exception('Failed to parse Groq response');
            }

            return [
                'amount' => (float) $extracted['amount'],
                'currency' => strtoupper($extracted['currency'] ?? 'EUR'),
                'description' => $extracted['description'] ?? 'Unknown purchase',
                'category' => $extracted['category'] ?? 'Other',
            ];
        } catch (\Exception $e) {
            Log::error('Groq API Error: '.$e->getMessage());

            throw new \Exception('Failed to analyze receipt: '.$e->getMessage());
        }
    }

    /**
     * Analyze a receipt image from S3 and extract expense information.
     *
     * @param  string  $s3Path  Path to the receipt image in S3
     * @param  string  $disk  The storage disk to use (default: 's3')
     * @return array{amount: float, currency: string, description: string, category: string}
     */
    public function analyzeReceiptFromS3(string $s3Path, string $disk = 's3'): array
    {
        // Download the file from S3 to a temporary location
        $tempPath = storage_path('app/temp/'.basename($s3Path));
        $tempDir = dirname($tempPath);

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // Download from S3
            $fileContents = Storage::disk($disk)->get($s3Path);

            if (! $fileContents) {
                throw new \Exception('Failed to download file from S3');
            }

            file_put_contents($tempPath, $fileContents);

            // Verify the file was written correctly
            if (! file_exists($tempPath) || filesize($tempPath) === 0) {
                throw new \Exception('Failed to write temporary file from S3 contents');
            }

            // Analyze the image
            $result = $this->analyzeReceipt($tempPath);

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $result;
        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            throw $e;
        }
    }
}
