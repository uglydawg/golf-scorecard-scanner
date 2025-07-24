<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    private string $currentProvider;

    /** @var array<string, mixed> */
    private array $providerConfig;

    public function __construct()
    {
        $this->currentProvider = config('scorecard-scanner.ocr.default', 'mock');
        $this->providerConfig = config('scorecard-scanner.ocr.providers.'.$this->currentProvider, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractText(string $imagePath): array
    {
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $fullPath = Storage::disk($storageDisk)->path($imagePath);

        return match ($this->currentProvider) {
            'mock' => $this->getMockOcrData(),
            'ocrspace' => $this->processWithOcrSpace($fullPath, $imagePath),
            'google' => $this->processWithGoogleVision($fullPath, $imagePath),
            'aws' => $this->processWithAwsTextract($fullPath, $imagePath),
            'openai' => $this->processWithOpenAI($fullPath, $imagePath),
            'openrouter' => $this->processWithOpenRouter($fullPath, $imagePath),
            default => $this->getMockOcrData(),
        };
    }

    /**
     * @return array<string, mixed>
     */
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

            throw new \Exception('OCR API request failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'provider' => 'ocrspace',
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return $this->getMockOcrData();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithGoogleVision(string $fullPath, string $imagePath): array
    {
        // Placeholder for Google Vision API integration
        Log::info('Google Vision API not yet implemented, using mock data');

        return $this->getMockOcrData();
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithAwsTextract(string $fullPath, string $imagePath): array
    {
        // Placeholder for AWS Textract integration
        Log::info('AWS Textract not yet implemented, using mock data');

        return $this->getMockOcrData();
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithOpenAI(string $fullPath, string $imagePath): array
    {
        $apiKey = $this->providerConfig['api_key'] ?? '';
        $model = $this->providerConfig['model'] ?? 'gpt-4o-mini';
        $maxTokens = $this->providerConfig['max_tokens'] ?? 4000;

        if (empty($apiKey)) {
            Log::warning('OpenAI API key not configured, using mock data');

            return $this->getMockOcrData();
        }

        try {
            // Encode image as base64
            $imageData = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);

            $response = Http::timeout($this->providerConfig['timeout'] ?? 60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'This is a golf scorecard image. Please extract ALL text from the image and format it as structured JSON with the following format:

{
  "course_name": "Name of the golf course",
  "date": "Date if visible",
  "players": ["List of player names"],
  "holes": [
    {
      "number": 1,
      "par": 4,
      "yardage": 350,
      "handicap": 10,
      "scores": [4, 5]
    }
  ],
  "totals": {
    "front_nine": [38, 36],
    "back_nine": [35, 37],
    "total": [73, 73]
  },
  "course_info": {
    "rating": "72.1",
    "slope": "113"
  }
}

Extract every visible number, name, and text. Pay special attention to:
- Course name at the top
- Hole numbers (1-18)
- Par values for each hole
- Yardages/distances
- Player names
- Individual hole scores
- Totals and subtotals
- Course rating and slope
- Any dates or other information

Be very thorough and accurate.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$imageData}",
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';

                return $this->processOpenAIResponse($content, $imagePath);
            }

            throw new \Exception('OpenAI API request failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('OpenAI OCR processing failed', [
                'provider' => 'openai',
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return $this->getMockOcrData();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithOpenRouter(string $fullPath, string $imagePath): array
    {
        $apiKey = $this->providerConfig['api_key'] ?? '';
        $model = $this->providerConfig['model'] ?? 'openai/gpt-4o-mini';
        $maxTokens = $this->providerConfig['max_tokens'] ?? 4000;
        $baseUrl = $this->providerConfig['base_url'] ?? 'https://openrouter.ai/api/v1';

        // Debug logging
        Log::info('OpenRouter Configuration Debug', [
            'provider' => $this->currentProvider,
            'api_key_present' => ! empty($apiKey),
            'api_key_length' => strlen($apiKey),
            'config_keys' => array_keys($this->providerConfig),
            'model' => $model,
        ]);

        if (empty($apiKey)) {
            Log::warning('OpenRouter API key not configured, using mock data', [
                'provider_config' => $this->providerConfig,
            ]);

            return $this->getMockOcrData();
        }

        try {
            // Encode image as base64
            $imageData = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);

            $response = Http::timeout($this->providerConfig['timeout'] ?? 60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'Golf Scorecard OCR Scanner',
                ])
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Extract all text from this golf scorecard image. Focus on:
- Course name and location
- Date of play
- Player names
- Hole numbers (1-18)
- Par values for each hole
- Handicap values for each hole
- Player scores for each hole
- Totals and subtotals
- Course rating and slope
- Any dates or other information

Return the text exactly as it appears, preserving layout and structure. Be very thorough and accurate.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$imageData}",
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';

                return $this->processOpenAIResponse($content, $imagePath);
            }

            throw new \Exception('OpenRouter API request failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('OpenRouter OCR processing failed', [
                'provider' => 'openrouter',
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            return $this->getMockOcrData();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processOpenAIResponse(string $content, string $imagePath): array
    {
        try {
            // Extract JSON from the response (GPT sometimes adds explanation text)
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                $parsedData = json_decode($jsonContent, true);

                if ($parsedData) {
                    return $this->formatOpenAIData($parsedData, $content);
                }
            }

            // If JSON parsing fails, treat the whole response as raw text
            return [
                'text' => $content,
                'raw_text' => $content,
                'confidence' => 90, // OpenAI is generally quite accurate (as percentage)
                'words' => $this->extractWordsFromText($content),
                'lines' => explode("\n", trim($content)),
                'structured_data' => null,
                'provider' => 'openrouter',
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to parse OpenAI response', [
                'error' => $e->getMessage(),
                'content' => substr($content, 0, 500), // Log first 500 chars
            ]);

            return [
                'text' => $content,
                'raw_text' => $content,
                'confidence' => 80,
                'words' => $this->extractWordsFromText($content),
                'lines' => explode("\n", trim($content)),
                'structured_data' => null,
                'provider' => 'openrouter',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $parsedData
     * @return array<string, mixed>
     */
    private function formatOpenAIData(array $parsedData, string $rawContent): array
    {
        // Create a formatted text representation from structured data
        $formattedText = [];

        if (! empty($parsedData['course_name'])) {
            $formattedText[] = $parsedData['course_name'];
        }

        if (! empty($parsedData['date'])) {
            $formattedText[] = 'Date: '.$parsedData['date'];
        }

        if (! empty($parsedData['players'])) {
            foreach ($parsedData['players'] as $i => $player) {
                $formattedText[] = 'Player '.($i + 1).': '.$player;
            }
        }

        // Add hole information
        if (! empty($parsedData['holes'])) {
            $formattedText[] = 'Hole  Par  Hdcp  Scores';
            foreach ($parsedData['holes'] as $hole) {
                $line = $hole['number'].'     '.$hole['par'];
                if (isset($hole['handicap'])) {
                    $line .= '    '.$hole['handicap'];
                }
                if (! empty($hole['scores'])) {
                    $line .= '    '.implode('   ', $hole['scores']);
                }
                $formattedText[] = $line;
            }
        }

        // Add totals
        if (! empty($parsedData['totals'])) {
            if (! empty($parsedData['totals']['front_nine'])) {
                $formattedText[] = 'OUT           '.implode('  ', $parsedData['totals']['front_nine']);
            }
            if (! empty($parsedData['totals']['back_nine'])) {
                $formattedText[] = 'IN            '.implode('  ', $parsedData['totals']['back_nine']);
            }
            if (! empty($parsedData['totals']['total'])) {
                $formattedText[] = 'TOTAL         '.implode('  ', $parsedData['totals']['total']);
            }
        }

        // Add course info
        if (! empty($parsedData['course_info'])) {
            if (! empty($parsedData['course_info']['slope'])) {
                $formattedText[] = 'Slope: '.$parsedData['course_info']['slope'];
            }
            if (! empty($parsedData['course_info']['rating'])) {
                $formattedText[] = 'Rating: '.$parsedData['course_info']['rating'];
            }
        }

        $finalText = implode("\n", $formattedText);

        return [
            'text' => $finalText,
            'raw_text' => $finalText,
            'confidence' => 95, // OpenAI with structured output is very reliable (as percentage)
            'words' => $this->extractWordsFromText($finalText),
            'lines' => $formattedText,
            'structured_data' => $parsedData, // This is the key advantage!
            'provider' => 'openrouter',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractWordsFromText(string $text): array
    {
        $words = [];
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($tokens as $i => $token) {
            $words[] = [
                'text' => trim($token, '.,!?;:'),
                'confidence' => 0.9,
                'bbox' => $i * 50, // Approximate positioning
            ];
        }

        return $words;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<string, mixed>  $word
     */
    private function calculateWordConfidence(array $word): float
    {
        // Simple confidence calculation based on word characteristics
        $text = $word['WordText'] ?? '';
        $height = $word['Height'] ?? 10;
        $width = $word['Width'] ?? 10;

        $baseConfidence = (float) ($this->providerConfig['confidence'] ?? 0.8);
        $confidence = $baseConfidence;

        // Adjust confidence based on text characteristics
        if (is_numeric($text)) {
            $confidence += 0.1; // Numbers are usually more reliable
        }

        if (is_string($text) && strlen($text) > 2) {
            $confidence += 0.05; // Longer words are more reliable
        }

        $heightValue = (int) $height;
        $widthValue = (int) $width;
        if ($heightValue > 15 && $widthValue > 15) {
            $confidence += 0.05; // Larger text is more reliable
        }

        return min($confidence, 1.0);
    }

    /**
     * @return array<string, mixed>
     */
    private function getMockOcrData(): array
    {
        $mockConfidence = (float) ($this->providerConfig['confidence'] ?? 0.95);
        $mockText = "PEBBLE BEACH GOLF LINKS\nChampionship Tees\nDate: 07/24/2024\nPlayer 1: John Doe\nPlayer 2: Jane Smith\nHole  Par  Hdcp  P1  P2\n1     4    10    4   5\n2     4    16    5   4\n3     4    4     3   4\n4     4    2     4   5\n5     3    14    3   2\n6     5    12    6   5\n7     3    8     4   3\n8     4    18    4   4\n9     4    6     5   4\nOUT   35         38  36\n10    4    11    4   4\n11    4    15    5   5\n12    3    17    3   3\n13    4    1     4   5\n14    5    3     5   6\n15    4    13    4   4\n16    4    9     3   4\n17    3    7     3   2\n18    4    5     4   4\nIN    35         35  37\nTOTAL 70         73  73\nSlope: 113  Rating: 72.1";

        return [
            'text' => $mockText,
            'raw_text' => $mockText,
            'confidence' => (int) ($mockConfidence * 100), // Convert to percentage
            'provider' => 'mock',
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
