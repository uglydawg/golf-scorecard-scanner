<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class OcrService
{
    private string $currentProvider;
    private array $providerConfig;

    public function __construct()
    {
        $this->currentProvider = config('scorecard-scanner.ocr.default', 'mock');
        $this->providerConfig = config('scorecard-scanner.ocr.providers.' . $this->currentProvider, []);
    }

    public function extractText(string $imagePath): array
    {
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $fullPath = Storage::disk($storageDisk)->path($imagePath);
        
        return match ($this->currentProvider) {
            'mock' => $this->getMockOcrData(),
            'ocrspace' => $this->processWithOcrSpace($fullPath, $imagePath),
            'google' => $this->processWithGoogleVision($fullPath, $imagePath),
            'aws' => $this->processWithAwsTextract($fullPath, $imagePath),
            default => $this->getMockOcrData(),
        };
    }

    private function processWithOcrSpace(string $fullPath, string $imagePath): array
    {
        $apiKey = $this->providerConfig['api_key'] ?? '';
        $apiUrl = $this->providerConfig['base_url'] ?? 'https://api.ocr.space/parse/image';
        
        if (empty($apiKey)) {
            return $this->getMockOcrData();
        }

        try {
            $response = Http::timeout($this->providerConfig['timeout'] ?? 30)
                ->attach('file', file_get_contents($fullPath), basename($imagePath))
                ->post($apiUrl, [
                    'apikey' => $apiKey,
                    'language' => $this->providerConfig['language'] ?? 'eng',
                    'isOverlayRequired' => true,
                    'detectOrientation' => true,
                    'scale' => true,
                    'isTable' => true,
                ]);

            if ($response->successful()) {
                return $this->processOcrResponse($response->json());
            }

            throw new \Exception('OCR API request failed: ' . $response->body());
            
        } catch (\Exception $e) {
            \Log::error('OCR processing failed', [
                'provider' => 'ocrspace',
                'error' => $e->getMessage(),
                'image_path' => $imagePath
            ]);
            
            return $this->getMockOcrData();
        }
    }

    private function processWithGoogleVision(string $fullPath, string $imagePath): array
    {
        // Placeholder for Google Vision API integration
        \Log::info('Google Vision API not yet implemented, using mock data');
        return $this->getMockOcrData();
    }

    private function processWithAwsTextract(string $fullPath, string $imagePath): array
    {
        // Placeholder for AWS Textract integration
        \Log::info('AWS Textract not yet implemented, using mock data');
        return $this->getMockOcrData();
    }

    private function processOcrResponse(array $response): array
    {
        $processedData = [
            'raw_text' => '',
            'confidence' => 0,
            'words' => [],
            'lines' => [],
        ];

        if (isset($response['ParsedResults']) && !empty($response['ParsedResults'])) {
            $result = $response['ParsedResults'][0];
            
            $processedData['raw_text'] = $result['ParsedText'] ?? '';
            $processedData['confidence'] = $result['TextOverlay']['HasOverlay'] ? 0.95 : 0.70;
            
            // Process text overlay data for word-level confidence
            if (isset($result['TextOverlay']['Lines'])) {
                foreach ($result['TextOverlay']['Lines'] as $line) {
                    $lineData = [
                        'text' => '',
                        'confidence' => 0.9,
                        'words' => []
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
        
        $baseConfidence = $this->providerConfig['confidence'] ?? 0.8;
        $confidence = $baseConfidence;
        
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
        $mockConfidence = $this->providerConfig['confidence'] ?? 0.95;
        
        return [
            'raw_text' => "PEBBLE BEACH GOLF LINKS\nChampionship Tees\nDate: 07/24/2024\nPlayer 1: John Doe\nPlayer 2: Jane Smith\nHole  Par  Hdcp  P1  P2\n1     4    10    4   5\n2     4    16    5   4\n3     4    4     3   4\n4     4    2     4   5\n5     3    14    3   2\n6     5    12    6   5\n7     3    8     4   3\n8     4    18    4   4\n9     4    6     5   4\nOUT   35         38  36\n10    4    11    4   4\n11    4    15    5   5\n12    3    17    3   3\n13    4    1     4   5\n14    5    3     5   6\n15    4    13    4   4\n16    4    9     3   4\n17    3    7     3   2\n18    4    5     4   4\nIN    35         35  37\nTOTAL 70         73  73\nSlope: 113  Rating: 72.1",
            'confidence' => $mockConfidence,
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
                    ]
                ],
                // ... more lines would be here
            ]
        ];
    }
}