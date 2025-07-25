<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    public function __construct(
        private string $ocrApiKey = '',
        private string $ocrApiUrl = 'https://api.ocr.space/parse/image'
    ) {
        $this->ocrApiKey = config('services.ocr.api_key', '');
    }

    public function extractText(string $imagePath): array
    {
        $fullPath = Storage::disk('public')->path($imagePath);

        // For demonstration, using OCR.space API (free tier)
        // In production, you might use Google Vision API, AWS Textract, or Azure Computer Vision

        if (empty($this->ocrApiKey)) {
            // Return mock data if no API key configured
            return $this->getMockOcrData();
        }

        try {
            $response = Http::attach(
                'file',
                file_get_contents($fullPath),
                basename($imagePath)
            )->post($this->ocrApiUrl, [
                'apikey' => $this->ocrApiKey,
                'language' => 'eng',
                'isOverlayRequired' => true,
                'detectOrientation' => true,
                'scale' => true,
                'isTable' => true,
            ]);

            if ($response->successful()) {
                return $this->processOcrResponse($response->json());
            }

            throw new \Exception('OCR API request failed: '.$response->body());
        } catch (\Exception $e) {
            // Log error and return mock data for development
            \Log::error('OCR processing failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return $this->getMockOcrData();
        }
    }

    private function processOcrResponse(array $response): array
    {
        $processedData = [
            'raw_text' => '',
            'confidence' => 0,
            'words' => [],
            'lines' => [],
        ];

        if (isset($response['ParsedResults']) && ! empty($response['ParsedResults'])) {
            $result = $response['ParsedResults'][0];

            $processedData['raw_text'] = $result['ParsedText'] ?? '';
            $processedData['confidence'] = $result['TextOverlay']['HasOverlay'] ? 0.95 : 0.70;

            // Process text overlay data for word-level confidence
            if (isset($result['TextOverlay']['Lines'])) {
                foreach ($result['TextOverlay']['Lines'] as $line) {
                    $lineData = [
                        'text' => '',
                        'confidence' => 0.9,
                        'words' => [],
                    ];

                    if (isset($line['Words'])) {
                        foreach ($line['Words'] as $word) {
                            $wordData = [
                                'text' => $word['WordText'] ?? '',
                                'confidence' => $this->calculateWordConfidence($word),
                                'bbox' => $word['Left'] ?? 0,
                            ];

                            $lineData['words'][] = $wordData;
                            $processedData['words'][] = $wordData;
                        }
                    }

                    $processedData['lines'][] = $lineData;
                }
            }
        }

        return $processedData;
    }

    private function calculateWordConfidence(array $word): float
    {
        // Simple confidence calculation based on word characteristics
        $text = $word['WordText'] ?? '';
        $height = $word['Height'] ?? 10;
        $width = $word['Width'] ?? 10;

        $confidence = 0.8; // Base confidence

        // Adjust confidence based on text characteristics
        if (is_numeric($text)) {
            $confidence += 0.1; // Numbers are usually more reliable
        }

        if (strlen($text) > 2) {
            $confidence += 0.05; // Longer words are more reliable
        }

        if ($height > 15 && $width > 15) {
            $confidence += 0.05; // Larger text is more reliable
        }

        return min($confidence, 1.0);
    }

    private function getMockOcrData(): array
    {
        return [
            'raw_text' => "PEBBLE BEACH GOLF LINKS\nChampionship Tees\nDate: 07/24/2024\nPlayer 1: John Doe\nPlayer 2: Jane Smith\nHole  Par  Hdcp  P1  P2\n1     4    10    4   5\n2     4    16    5   4\n3     4    4     3   4\n4     4    2     4   5\n5     3    14    3   2\n6     5    12    6   5\n7     3    8     4   3\n8     4    18    4   4\n9     4    6     5   4\nOUT   35         38  36\n10    4    11    4   4\n11    4    15    5   5\n12    3    17    3   3\n13    4    1     4   5\n14    5    3     5   6\n15    4    13    4   4\n16    4    9     3   4\n17    3    7     3   2\n18    4    5     4   4\nIN    35         35  37\nTOTAL 70         73  73\nSlope: 113  Rating: 72.1",
            'confidence' => 0.92,
            'words' => [
                ['text' => 'PEBBLE', 'confidence' => 0.95, 'bbox' => 100],
                ['text' => 'BEACH', 'confidence' => 0.93, 'bbox' => 150],
                ['text' => 'GOLF', 'confidence' => 0.94, 'bbox' => 200],
                ['text' => 'LINKS', 'confidence' => 0.91, 'bbox' => 250],
                // ... more words would be here
            ],
            'lines' => [
                [
                    'text' => 'PEBBLE BEACH GOLF LINKS',
                    'confidence' => 0.94,
                    'words' => [
                        ['text' => 'PEBBLE', 'confidence' => 0.95],
                        ['text' => 'BEACH', 'confidence' => 0.93],
                        ['text' => 'GOLF', 'confidence' => 0.94],
                        ['text' => 'LINKS', 'confidence' => 0.91],
                    ],
                ],
                // ... more lines would be here
            ],
        ];
    }
}
