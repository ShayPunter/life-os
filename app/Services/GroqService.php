<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

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
     * @return array{amount: float, description: string, category: string}
     */
    public function analyzeReceipt(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            throw new \Exception('Image file not found');
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

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
                                    'text' => 'Analyze this receipt image and extract the following information in JSON format: total amount (as a number), description (what was purchased - be brief, max 100 characters), and category (choose one from: Food, Transportation, Shopping, Utilities, Entertainment, Healthcare, Other). Return ONLY valid JSON with keys: amount, description, category. Do not include any markdown formatting or code blocks.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$imageData}",
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
                'description' => $extracted['description'] ?? 'Unknown purchase',
                'category' => $extracted['category'] ?? 'Other',
            ];
        } catch (\Exception $e) {
            Log::error('Groq API Error: '.$e->getMessage());

            throw new \Exception('Failed to analyze receipt: '.$e->getMessage());
        }
    }
}
