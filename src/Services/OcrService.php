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

    private bool $useEnhancedPrompt;

    private ?TrainingDataService $trainingDataService;

    public function __construct(?TrainingDataService $trainingDataService = null)
    {
        $this->currentProvider = config('scorecard-scanner.ocr.default', 'mock');
        $this->providerConfig = config('scorecard-scanner.ocr.providers.'.$this->currentProvider, []);
        $this->useEnhancedPrompt = config('scorecard-scanner.ocr.enhanced_prompt_enabled', false);
        $this->trainingDataService = $trainingDataService;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractText(string $imagePath): array
    {
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $fullPath = Storage::disk($storageDisk)->path($imagePath);

        $startTime = microtime(true);

        $result = match ($this->currentProvider) {
            'mock' => $this->getMockOcrData(),
            'ocrspace' => $this->processWithOcrSpace($fullPath, $imagePath),
            'google' => $this->processWithGoogleVision($fullPath, $imagePath),
            'aws' => $this->processWithAwsTextract($fullPath, $imagePath),
            'openai' => $this->processWithOpenAI($fullPath, $imagePath),
            'openrouter' => $this->processWithOpenRouter($fullPath, $imagePath),
            default => $this->getMockOcrData(),
        };

        // Add processing metadata
        $processingTime = (microtime(true) - $startTime) * 1000;
        $result['processing_time_ms'] = (int) $processingTime;
        $result['provider'] = $this->currentProvider;
        $result['enhanced_format'] = $this->useEnhancedPrompt;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithOcrSpace(string $fullPath, string $imagePath): array
    {
        $apiKey = $this->providerConfig['api_key'] ?? '';
        $apiUrl = $this->providerConfig['base_url'] ?? 'https://api.ocr.space/parse/image';

        if (empty($apiKey)) {
            throw new \Exception('OCR.space API key not configured. Set OCRSPACE_API_KEY in your environment.');
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

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithGoogleVision(string $fullPath, string $imagePath): array
    {
        // Placeholder for Google Vision API integration
        throw new \Exception('Google Vision API not yet implemented. Configure Google Cloud credentials or use a different provider.');
    }

    /**
     * @return array<string, mixed>
     */
    private function processWithAwsTextract(string $fullPath, string $imagePath): array
    {
        // Placeholder for AWS Textract integration
        throw new \Exception('AWS Textract not yet implemented. Configure AWS credentials or use a different provider.');
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
            throw new \Exception('OpenAI API key not configured. Set OPENAI_API_KEY in your environment.');
        }

        try {
            // Encode image as base64
            $imageData = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);

            $response = Http::timeout((int) ($this->providerConfig['timeout'] ?? 60))
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
                                    'text' => $this->useEnhancedPrompt
                                        ? $this->getEnhancedGolfScorecardExtractionPrompt()
                                        : $this->getGolfScorecardExtractionPrompt(),
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

                return $this->useEnhancedPrompt
                    ? $this->processEnhancedOpenAIResponse($content, $imagePath)
                    : $this->processOpenAIResponse($content, $imagePath);
            }

            throw new \Exception('OpenAI API request failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('OpenAI OCR processing failed', [
                'provider' => 'openai',
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            throw $e;
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
            throw new \Exception('OpenRouter API key not configured. Set OPENROUTER_API_KEY in your environment.');
        }

        try {
            // Encode image as base64
            $imageData = base64_encode(file_get_contents($fullPath));
            $mimeType = mime_content_type($fullPath);

            $response = Http::timeout((int) ($this->providerConfig['timeout'] ?? 60))
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
                                    'text' => $this->useEnhancedPrompt
                                        ? $this->getEnhancedGolfScorecardExtractionPrompt()
                                        : $this->getGolfScorecardExtractionPrompt(),
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

                return $this->useEnhancedPrompt
                    ? $this->processEnhancedOpenAIResponse($content, $imagePath)
                    : $this->processOpenAIResponse($content, $imagePath);
            }

            throw new \Exception('OpenRouter API request failed: '.$response->body());
        } catch (\Exception $e) {
            Log::error('OpenRouter OCR processing failed', [
                'provider' => 'openrouter',
                'error' => $e->getMessage(),
                'image_path' => $imagePath,
            ]);

            throw $e;
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
        // Create a formatted text representation from comprehensive golf course data
        $formattedText = [];

        // Course identification
        if (! empty($parsedData['course_name'])) {
            $formattedText[] = $parsedData['course_name'];
        }

        if (! empty($parsedData['course_location'])) {
            $formattedText[] = $parsedData['course_location'];
        }

        if (! empty($parsedData['tee_name'])) {
            $formattedText[] = $parsedData['tee_name'].' Tees';
        }

        if (! empty($parsedData['date'])) {
            $formattedText[] = 'Date: '.$parsedData['date'];
        }

        // Course ratings
        if (! empty($parsedData['course_rating']) || ! empty($parsedData['slope_rating'])) {
            $ratingLine = '';
            if (! empty($parsedData['course_rating'])) {
                $ratingLine .= 'Rating: '.$parsedData['course_rating'];
            }
            if (! empty($parsedData['slope_rating'])) {
                $ratingLine .= ($ratingLine ? '  ' : '').'Slope: '.$parsedData['slope_rating'];
            }
            $formattedText[] = $ratingLine;
        }

        // Total par and yardage
        if (! empty($parsedData['total_par']) || ! empty($parsedData['total_yardage'])) {
            $totalLine = '';
            if (! empty($parsedData['total_par'])) {
                $totalLine .= 'Par: '.$parsedData['total_par'];
            }
            if (! empty($parsedData['total_yardage'])) {
                $totalLine .= ($totalLine ? '  ' : '').'Yardage: '.$parsedData['total_yardage'];
            }
            $formattedText[] = $totalLine;
        }

        // Players
        if (! empty($parsedData['players'])) {
            foreach ($parsedData['players'] as $i => $player) {
                $formattedText[] = 'Player '.($i + 1).': '.$player;
            }
        }

        // Hole information with enhanced details
        if (! empty($parsedData['holes'])) {
            $formattedText[] = 'Hole  Par  Hdcp  Yds  Scores';
            foreach ($parsedData['holes'] as $hole) {
                $line = str_pad((string) $hole['number'], 4);
                $line .= str_pad((string) ($hole['par'] ?? ''), 4);
                $line .= str_pad((string) ($hole['handicap'] ?? ''), 5);
                $line .= str_pad((string) ($hole['yardage'] ?? ''), 5);

                if (! empty($hole['scores'])) {
                    $line .= ' '.implode('  ', $hole['scores']);
                }
                $formattedText[] = $line;
            }
        }

        // Front nine totals
        if (! empty($parsedData['front_nine'])) {
            $frontLine = 'OUT   ';
            if (! empty($parsedData['front_nine']['par'])) {
                $frontLine .= str_pad((string) $parsedData['front_nine']['par'], 4);
            }
            $frontLine .= str_pad('', 5); // handicap spacing
            if (! empty($parsedData['front_nine']['yardage'])) {
                $frontLine .= str_pad((string) $parsedData['front_nine']['yardage'], 5);
            }
            if (! empty($parsedData['front_nine']['scores'])) {
                $frontLine .= ' '.implode('  ', $parsedData['front_nine']['scores']);
            }
            $formattedText[] = $frontLine;
        }

        // Back nine totals
        if (! empty($parsedData['back_nine'])) {
            $backLine = 'IN    ';
            if (! empty($parsedData['back_nine']['par'])) {
                $backLine .= str_pad((string) $parsedData['back_nine']['par'], 4);
            }
            $backLine .= str_pad('', 5); // handicap spacing
            if (! empty($parsedData['back_nine']['yardage'])) {
                $backLine .= str_pad((string) $parsedData['back_nine']['yardage'], 5);
            }
            if (! empty($parsedData['back_nine']['scores'])) {
                $backLine .= ' '.implode('  ', $parsedData['back_nine']['scores']);
            }
            $formattedText[] = $backLine;
        }

        // Overall totals
        if (! empty($parsedData['totals'])) {
            $totalLine = 'TOTAL ';
            if (! empty($parsedData['totals']['par'])) {
                $totalLine .= str_pad((string) $parsedData['totals']['par'], 4);
            }
            $totalLine .= str_pad('', 5); // handicap spacing
            if (! empty($parsedData['totals']['yardage'])) {
                $totalLine .= str_pad((string) $parsedData['totals']['yardage'], 5);
            }
            if (! empty($parsedData['totals']['scores'])) {
                $totalLine .= ' '.implode('  ', $parsedData['totals']['scores']);
            }
            $formattedText[] = $totalLine;
        }

        // Additional information
        if (! empty($parsedData['additional_info'])) {
            $additional = $parsedData['additional_info'];
            if (! empty($additional['tournament_name'])) {
                $formattedText[] = 'Tournament: '.$additional['tournament_name'];
            }
            if (! empty($additional['designer'])) {
                $formattedText[] = 'Designer: '.$additional['designer'];
            }
            if (! empty($additional['established'])) {
                $formattedText[] = 'Established: '.$additional['established'];
            }
            if (! empty($additional['phone'])) {
                $formattedText[] = 'Phone: '.$additional['phone'];
            }
            if (! empty($additional['website'])) {
                $formattedText[] = 'Website: '.$additional['website'];
            }
        }

        $finalText = implode("\n", $formattedText);

        // Enhanced structured data with extracted golf course properties
        $enhancedData = $this->enhanceGolfCourseData($parsedData);

        return [
            'text' => $finalText,
            'raw_text' => $finalText,
            'confidence' => 95, // OpenAI with structured output is very reliable (as percentage)
            'words' => $this->extractWordsFromText($finalText),
            'lines' => $formattedText,
            'structured_data' => $enhancedData,
            'golf_course_properties' => $this->extractGolfCourseProperties($enhancedData),
            'provider' => $this->currentProvider,
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

    private function getGolfScorecardExtractionPrompt(): string
    {
        return 'You are a specialized golf scorecard OCR system. Extract ALL information from this golf scorecard image and format as structured JSON. This data will populate a golf course database that stores course information, round data, and individual hole scores.

REQUIRED JSON OUTPUT FORMAT:
{
  "course_name": "Full official name of the golf course",
  "course_location": "City, State/Province, Country (or full address if visible)",
  "date": "Date of play in YYYY-MM-DD format if visible",
  "tee_name": "Specific tee designation (e.g., Championship, Blue, White, Red, Gold, Black, Tips)",
  "course_rating": 72.1,
  "slope_rating": 113,
  "par_values": [4, 3, 5, 4, 4, 3, 4, 5, 4, 4, 3, 5, 4, 4, 3, 4, 5, 4],
  "handicap_values": [10, 18, 2, 14, 6, 16, 8, 4, 12, 1, 17, 3, 13, 7, 15, 9, 5, 11],
  "yardages": [350, 155, 520, 380, 410, 165, 425, 545, 390, 420, 180, 565, 375, 445, 170, 400, 510, 385],
  "total_par": 72,
  "total_yardage": 6600,
  "players": ["Player Name 1", "Player Name 2", "Player Name 3", "Player Name 4"],
  "player_scores": {
    "Player Name 1": {
      "hole_scores": [4, 3, 5, 4, 5, 3, 4, 6, 4, 4, 2, 5, 4, 4, 3, 4, 5, 4],
      "front_nine_score": 38,
      "back_nine_score": 35,
      "total_score": 73
    },
    "Player Name 2": {
      "hole_scores": [5, 4, 6, 4, 4, 3, 5, 5, 4, 3, 3, 4, 5, 4, 3, 4, 6, 4],
      "front_nine_score": 40,
      "back_nine_score": 36,
      "total_score": 76
    }
  },
  "weather": "Weather conditions if noted on scorecard",
  "additional_notes": "Any special notes, tournament info, or course conditions"
}

CRITICAL EXTRACTION PRIORITIES:

1. COURSE IDENTIFICATION (MANDATORY):
   - Course name: Look for large prominent text, usually at the top
   - Location: City, state, country, or full address
   - Tee designation: Look for tee names, colors, or markers (Men\'s/Women\'s, Championship, etc.)

2. HOLE-BY-HOLE DATA (ABSOLUTELY CRITICAL):
   - par_values: Array of exactly 18 integers (3, 4, or 5) - holes 1-18 in order
   - handicap_values: Array of exactly 18 integers (1-18) showing difficulty ranking
   - yardages: Array of exactly 18 integers showing distance for the specific tee
   - Ensure arrays are in hole order 1, 2, 3... 18 (not front nine then back nine)

3. COURSE RATINGS (ESSENTIAL FOR DATABASE):
   - course_rating: Decimal number (typically 67.0-77.0) - difficulty for scratch golfer
   - slope_rating: Integer (55-155, typically 90-140) - relative difficulty measure
   - total_par: Sum of all par values (typically 70-72)
   - total_yardage: Sum of all hole yardages

4. PLAYER SCORING DATA:
   - Extract all player names from scorecard header
   - Individual hole scores for each player (18 integers per player)
   - Calculate front nine (holes 1-9) and back nine (holes 10-18) subtotals
   - Calculate total score for each player

5. METADATA:
   - Date of play if visible
   - Weather conditions if noted
   - Tournament or event information
   - Any special rules or notes

VALIDATION REQUIREMENTS:
- par_values must contain exactly 18 integers between 3-5
- handicap_values must contain exactly 18 unique integers from 1-18
- yardages must contain exactly 18 positive integers
- total_par must equal sum of par_values
- Player scores must be reasonable (typically 60-120 per round)
- Course rating typically ranges 67.0-77.0
- Slope rating must be 55-155

COMMON SCORECARD LAYOUTS:
- Front nine (holes 1-9) often on left side or top half
- Back nine (holes 10-18) often on right side or bottom half  
- Multiple tee yardages may be shown in columns
- Par and handicap usually in dedicated rows
- Player scores in grid format with totals

Extract every number with extreme accuracy. Golf handicap calculations depend on precise par values, handicap rankings, and course ratings. If any required data is unclear or missing, note it in additional_notes but still provide your best interpretation of visible information.';
    }

    private function getEnhancedGolfScorecardExtractionPrompt(): string
    {
        return 'You are a specialized golf scorecard OCR system with advanced AI capabilities. Extract comprehensive golf course data from this scorecard image and return it as structured JSON matching the exact schema below. This data will populate a professional golf course database with 95%+ accuracy requirements.

ENHANCED JSON SCHEMA (MANDATORY FORMAT):
{
  "course_information": {
    "course_name": "Full official name of the golf course",
    "location": {
      "address": "Street address if visible",
      "city": "City name", 
      "state": "State/Province",
      "country": "Country code (US, CA, etc.)",
      "postal_code": "ZIP/postal code if visible"
    },
    "architect": "Course architect/designer name if mentioned",
    "established_year": 1925,
    "phone": "Phone number if visible",
    "website": "Website URL if visible",
    "description": "Any course description or tagline",
    "confidence": 0.95
  },
  "tee_boxes": [
    {
      "tee_name": "Championship",
      "tee_color": "Black",
      "gender": "Men",
      "par_values": [4, 3, 5, 4, 4, 3, 4, 5, 4, 4, 3, 5, 4, 4, 3, 4, 5, 4],
      "handicap_values": [1, 17, 3, 13, 7, 15, 9, 5, 11, 2, 18, 4, 14, 6, 16, 8, 10, 12],
      "yardages": [420, 155, 545, 385, 410, 165, 425, 520, 395, 440, 180, 565, 375, 445, 170, 400, 510, 385],
      "course_rating": 72.8,
      "slope_rating": 142,
      "total_par": 72,
      "total_yardage": 6935,
      "confidence": 0.92
    }
  ],
  "player_scores": [
    {
      "player_name": "John Doe",
      "hole_scores": [4, 3, 6, 5, 4, 3, 4, 6, 4, 4, 2, 5, 4, 4, 3, 4, 5, 4],
      "front_nine_total": 39,
      "back_nine_total": 35,
      "total_score": 74,
      "confidence": 0.88
    }
  ],
  "round_metadata": {
    "date_played": "2024-07-24",
    "weather_conditions": "Clear, 75°F, Light Wind",
    "tournament_name": "Club Championship",
    "notes": "Any special notes or conditions"
  }
}

CRITICAL EXTRACTION REQUIREMENTS:

1. COURSE INFORMATION (MANDATORY):
   - Extract complete course identification with high confidence
   - Include architect and establishment year if visible
   - Parse location into structured components (city, state, country)
   - Capture contact information (phone, website) if present

2. TEE BOX CONFIGURATIONS (ESSENTIAL):
   - Identify ALL tee boxes shown on scorecard (Men\'s, Ladies\', Championship, etc.)
   - For EACH tee box, extract:
     * par_values: Exactly 18 integers (3-6) in hole order 1-18
     * handicap_values: Exactly 18 unique integers (1-18) in hole order
     * yardages: Exactly 18 integers (50-700 range) for that specific tee
     * course_rating: Decimal 67.0-77.0 range
     * slope_rating: Integer 55-155 range
   - Create separate tee_box object for each tee configuration

3. MULTI-TEE RECOGNITION:
   - Many scorecards show multiple yardage columns for different tees
   - Championship/Blue/White/Red tees may have different ratings
   - Men\'s vs Ladies\' tees have different course/slope ratings
   - Extract ALL visible tee configurations as separate objects

4. PLAYER SCORE EXTRACTION:
   - Extract ALL player names from scorecard
   - For each player, capture hole-by-hole scores (18 integers)
   - Calculate and validate front nine, back nine, and total scores
   - Only include reasonable scores (1-15 per hole, 60-120 total)

5. ENHANCED DATA VALIDATION:
   - par_values: Must be exactly 18 integers, each 3-6
   - handicap_values: Must be exactly 18 unique integers 1-18
   - yardages: Must be exactly 18 integers, reasonable range 50-700
   - course_rating: Must be 67.0-77.0 decimal range
   - slope_rating: Must be 55-155 integer range
   - total_par: Must equal sum of par_values (typically 70-72)
   - total_yardage: Must equal sum of yardages
   - player_scores: Each hole score 1-15, total score reasonable

6. CONFIDENCE SCORING:
   - Provide confidence (0.0-1.0) for each major data section
   - Higher confidence for clear, printed text
   - Lower confidence for handwritten or unclear data
   - Overall confidence should reflect extraction quality

SCORECARD LAYOUT INTELLIGENCE:
- Front nine (holes 1-9) typically left side or top section
- Back nine (holes 10-18) typically right side or bottom section
- Multiple tee yardages shown in columns (Blue: 6800, White: 6200, Red: 5400)
- Par and handicap values usually in dedicated rows
- Player scores in grid format with running totals
- Course ratings often at bottom with tee-specific values

EXTRACTION PRIORITIES:
1. Course name and location (100% required)
2. Tee box data with complete 18-hole arrays (95%+ accuracy required)
3. Course and slope ratings for each tee (essential for handicap calculations)
4. Player scores if scorecard is completed (extract all visible)
5. Tournament or round metadata if present

Return ONLY the JSON object with no additional text. Ensure all numeric arrays contain exactly 18 values in hole order 1-18. If data is unclear, provide best interpretation but flag with lower confidence score.';
    }

    /**
     * @return array<string, mixed>
     */
    private function processEnhancedOpenAIResponse(string $content, string $imagePath): array
    {
        try {
            // Extract JSON from the response (GPT sometimes adds explanation text)
            $jsonStart = strpos($content, '{');
            $jsonEnd = strrpos($content, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
                $parsedData = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

                if ($parsedData) {
                    // Validate the enhanced data structure
                    $validatedData = $this->validateGolfCourseData($parsedData);

                    // Process multi-tee box configurations
                    if (isset($validatedData['tee_boxes'])) {
                        $validatedData['tee_boxes'] = $this->processTeeBoxConfigurations($validatedData['tee_boxes']);
                    }

                    // Process player scores
                    if (isset($validatedData['player_scores'])) {
                        $validatedData['player_scores'] = $this->processPlayerScores($validatedData['player_scores']);
                    }

                    // Calculate overall confidence
                    $validatedData['overall_confidence'] = $this->calculateOverallConfidence($validatedData);

                    return $this->formatEnhancedData($validatedData, $content);
                }
            }

            throw new \InvalidArgumentException('Invalid JSON response from OCR provider');
        } catch (\Exception $e) {
            Log::warning('Failed to parse enhanced OpenAI response', [
                'error' => $e->getMessage(),
                'content' => substr($content, 0, 500), // Log first 500 chars
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateGolfCourseData(array $data): array
    {
        $validationErrors = [];
        $validatedData = $data;

        // Validate tee boxes
        if (isset($data['tee_boxes']) && is_array($data['tee_boxes'])) {
            foreach ($data['tee_boxes'] as $index => $teeBox) {
                // Validate par values
                if (isset($teeBox['par_values'])) {
                    if (! is_array($teeBox['par_values']) || count($teeBox['par_values']) !== 18) {
                        $validationErrors[] = "Tee box {$index}: par_values must contain exactly 18 values";
                    } else {
                        foreach ($teeBox['par_values'] as $par) {
                            if (! is_numeric($par) || $par < 3 || $par > 6) {
                                $validationErrors[] = "Tee box {$index}: par values must be 3-6";
                                break;
                            }
                        }
                    }
                }

                // Validate handicap values
                if (isset($teeBox['handicap_values'])) {
                    if (! is_array($teeBox['handicap_values']) || count($teeBox['handicap_values']) !== 18) {
                        $validationErrors[] = "Tee box {$index}: handicap_values must contain exactly 18 values";
                    } else {
                        $uniqueHandicaps = array_unique($teeBox['handicap_values']);
                        if (count($uniqueHandicaps) !== 18) {
                            $validationErrors[] = "Tee box {$index}: handicap values must be unique integers 1-18";
                        }
                        foreach ($teeBox['handicap_values'] as $handicap) {
                            if (! is_numeric($handicap) || $handicap < 1 || $handicap > 18) {
                                $validationErrors[] = "Tee box {$index}: handicap values must be 1-18";
                                break;
                            }
                        }
                    }
                }

                // Validate yardages
                if (isset($teeBox['yardages'])) {
                    if (! is_array($teeBox['yardages']) || count($teeBox['yardages']) !== 18) {
                        $validationErrors[] = "Tee box {$index}: yardages must contain exactly 18 values";
                    } else {
                        foreach ($teeBox['yardages'] as $yardage) {
                            if (! is_numeric($yardage) || $yardage < 50 || $yardage > 700) {
                                $validationErrors[] = "Tee box {$index}: yardages must be 50-700 range";
                                break;
                            }
                        }
                    }
                }

                // Validate course rating
                if (isset($teeBox['course_rating'])) {
                    $rating = (float) $teeBox['course_rating'];
                    if ($rating < 67.0 || $rating > 77.0) {
                        $validationErrors[] = "Tee box {$index}: course_rating must be 67.0-77.0 range";
                    }
                }

                // Validate slope rating
                if (isset($teeBox['slope_rating'])) {
                    $slope = (int) $teeBox['slope_rating'];
                    if ($slope < 55 || $slope > 155) {
                        $validationErrors[] = "Tee box {$index}: slope_rating must be 55-155 range";
                    }
                }
            }
        }

        // Validate player scores
        if (isset($data['player_scores']) && is_array($data['player_scores'])) {
            foreach ($data['player_scores'] as $index => $player) {
                if (isset($player['hole_scores']) && is_array($player['hole_scores'])) {
                    if (count($player['hole_scores']) !== 18) {
                        $validationErrors[] = "Player {$index}: hole_scores must contain exactly 18 values";
                    } else {
                        foreach ($player['hole_scores'] as $score) {
                            if (! is_numeric($score) || $score < 1 || $score > 15) {
                                $validationErrors[] = "Player {$index}: hole scores must be 1-15 range";
                                break;
                            }
                        }
                    }
                }
            }
        }

        $validatedData['validation_errors'] = $validationErrors;

        return $validatedData;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teeBoxes
     * @return array<int, array<string, mixed>>
     */
    private function processTeeBoxConfigurations(array $teeBoxes): array
    {
        $processedTeeBoxes = [];

        foreach ($teeBoxes as $teeBox) {
            $processed = $teeBox;

            // Normalize tee name
            if (isset($processed['tee_name'])) {
                $processed['tee_name'] = $this->normalizeTeeNames($processed['tee_name']);
            }

            // Calculate totals if not provided
            if (isset($processed['par_values']) && is_array($processed['par_values'])) {
                $processed['total_par'] = array_sum($processed['par_values']);
            }

            if (isset($processed['yardages']) && is_array($processed['yardages'])) {
                $processed['total_yardage'] = array_sum($processed['yardages']);
            }

            // Add front/back nine breakdowns
            if (isset($processed['par_values']) && count($processed['par_values']) === 18) {
                $processed['front_nine_par'] = array_sum(array_slice($processed['par_values'], 0, 9));
                $processed['back_nine_par'] = array_sum(array_slice($processed['par_values'], 9, 9));
            }

            if (isset($processed['yardages']) && count($processed['yardages']) === 18) {
                $processed['front_nine_yardage'] = array_sum(array_slice($processed['yardages'], 0, 9));
                $processed['back_nine_yardage'] = array_sum(array_slice($processed['yardages'], 9, 9));
            }

            $processedTeeBoxes[] = $processed;
        }

        return $processedTeeBoxes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $playerScores
     * @return array<int, array<string, mixed>>
     */
    private function processPlayerScores(array $playerScores): array
    {
        $processedScores = [];

        foreach ($playerScores as $player) {
            $processed = $player;

            // Calculate totals if not provided
            if (isset($processed['hole_scores']) && is_array($processed['hole_scores']) && count($processed['hole_scores']) === 18) {
                $frontNine = array_slice($processed['hole_scores'], 0, 9);
                $backNine = array_slice($processed['hole_scores'], 9, 9);

                $processed['front_nine_total'] = array_sum($frontNine);
                $processed['back_nine_total'] = array_sum($backNine);
                $processed['total_score'] = $processed['front_nine_total'] + $processed['back_nine_total'];
            }

            $processedScores[] = $processed;
        }

        return $processedScores;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculateOverallConfidence(array $data): float
    {
        $confidenceValues = [];

        // Collect confidence scores from different sections
        if (isset($data['course_information']['confidence'])) {
            $confidenceValues[] = (float) $data['course_information']['confidence'];
        }

        if (isset($data['tee_boxes']) && is_array($data['tee_boxes'])) {
            foreach ($data['tee_boxes'] as $teeBox) {
                if (isset($teeBox['confidence'])) {
                    $confidenceValues[] = (float) $teeBox['confidence'];
                }
            }
        }

        if (isset($data['player_scores']) && is_array($data['player_scores'])) {
            foreach ($data['player_scores'] as $player) {
                if (isset($player['confidence'])) {
                    $confidenceValues[] = (float) $player['confidence'];
                }
            }
        }

        return count($confidenceValues) > 0 ? array_sum($confidenceValues) / count($confidenceValues) : 0.8;
    }

    /**
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>
     */
    private function formatEnhancedData(array $validatedData, string $rawContent): array
    {
        // Create formatted text representation for backward compatibility
        $formattedText = $this->createFormattedTextFromEnhancedData($validatedData);

        return [
            'text' => $formattedText,
            'raw_text' => $formattedText,
            'confidence' => (int) (($validatedData['overall_confidence'] ?? 0.9) * 100),
            'words' => $this->extractWordsFromText($formattedText),
            'lines' => explode("\n", trim($formattedText)),
            'structured_data' => $validatedData,
            'golf_course_properties' => $this->extractEnhancedGolfProperties($validatedData),
            'provider' => $this->currentProvider,
            'enhanced_format' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createFormattedTextFromEnhancedData(array $data): string
    {
        $lines = [];

        // Course information
        if (isset($data['course_information'])) {
            $courseInfo = $data['course_information'];

            if (! empty($courseInfo['course_name'])) {
                $lines[] = $courseInfo['course_name'];
            }

            if (isset($courseInfo['location'])) {
                $location = $courseInfo['location'];
                $locationParts = array_filter([
                    $location['city'] ?? '',
                    $location['state'] ?? '',
                    $location['country'] ?? '',
                ]);
                if (! empty($locationParts)) {
                    $lines[] = implode(', ', $locationParts);
                }
            }

            if (! empty($courseInfo['architect'])) {
                $lines[] = 'Architect: '.$courseInfo['architect'];
            }

            if (! empty($courseInfo['established_year'])) {
                $lines[] = 'Established: '.$courseInfo['established_year'];
            }
        }

        // Tee box information
        if (isset($data['tee_boxes']) && is_array($data['tee_boxes'])) {
            foreach ($data['tee_boxes'] as $teeBox) {
                $lines[] = '';
                $lines[] = ($teeBox['tee_name'] ?? 'Unknown').' Tees';

                if (isset($teeBox['course_rating']) || isset($teeBox['slope_rating'])) {
                    $ratingLine = '';
                    if (! empty($teeBox['course_rating'])) {
                        $ratingLine .= 'Rating: '.$teeBox['course_rating'];
                    }
                    if (! empty($teeBox['slope_rating'])) {
                        $ratingLine .= ($ratingLine ? '  ' : '').'Slope: '.$teeBox['slope_rating'];
                    }
                    $lines[] = $ratingLine;
                }

                if (isset($teeBox['total_par']) || isset($teeBox['total_yardage'])) {
                    $totalLine = '';
                    if (! empty($teeBox['total_par'])) {
                        $totalLine .= 'Par: '.$teeBox['total_par'];
                    }
                    if (! empty($teeBox['total_yardage'])) {
                        $totalLine .= ($totalLine ? '  ' : '').'Yardage: '.$teeBox['total_yardage'];
                    }
                    $lines[] = $totalLine;
                }
            }
        }

        // Player scores
        if (isset($data['player_scores']) && is_array($data['player_scores'])) {
            $lines[] = '';
            $lines[] = 'Player Scores:';
            foreach ($data['player_scores'] as $player) {
                if (! empty($player['player_name']) && ! empty($player['total_score'])) {
                    $lines[] = $player['player_name'].': '.$player['total_score'];
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractEnhancedGolfProperties(array $data): array
    {
        $properties = [];

        // Course information
        if (isset($data['course_information'])) {
            $courseInfo = $data['course_information'];
            $properties['course_name'] = $courseInfo['course_name'] ?? null;
            $properties['architect'] = $courseInfo['architect'] ?? null;
            $properties['established_year'] = $courseInfo['established_year'] ?? null;
            $properties['phone'] = $courseInfo['phone'] ?? null;
            $properties['website'] = $courseInfo['website'] ?? null;

            // Location data
            if (isset($courseInfo['location'])) {
                $properties['location'] = $courseInfo['location'];
                $properties['course_location'] = implode(', ', array_filter([
                    $courseInfo['location']['city'] ?? '',
                    $courseInfo['location']['state'] ?? '',
                    $courseInfo['location']['country'] ?? '',
                ]));
            }
        }

        // Tee box data (use first tee box as primary)
        if (isset($data['tee_boxes'][0])) {
            $primaryTee = $data['tee_boxes'][0];
            $properties['tee_name'] = $primaryTee['tee_name'] ?? null;
            $properties['course_rating'] = $primaryTee['course_rating'] ?? null;
            $properties['slope_rating'] = $primaryTee['slope_rating'] ?? null;
            $properties['total_par'] = $primaryTee['total_par'] ?? null;
            $properties['total_yardage'] = $primaryTee['total_yardage'] ?? null;
            $properties['par_values'] = $primaryTee['par_values'] ?? [];
            $properties['handicap_values'] = $primaryTee['handicap_values'] ?? [];
            $properties['yardage_values'] = $primaryTee['yardages'] ?? [];
        }

        // All tee boxes
        $properties['tee_boxes'] = $data['tee_boxes'] ?? [];

        // Player data
        if (isset($data['player_scores'])) {
            $properties['players'] = array_column($data['player_scores'], 'player_name');
            $properties['player_scores'] = $data['player_scores'];
        }

        // Round metadata
        if (isset($data['round_metadata'])) {
            $metadata = $data['round_metadata'];
            $properties['date_played'] = $metadata['date_played'] ?? null;
            $properties['weather_conditions'] = $metadata['weather_conditions'] ?? null;
            $properties['tournament_name'] = $metadata['tournament_name'] ?? null;
            $properties['notes'] = $metadata['notes'] ?? null;
        }

        // Data quality metrics
        $properties['overall_confidence'] = $data['overall_confidence'] ?? 0.8;
        $properties['validation_errors'] = $data['validation_errors'] ?? [];
        $properties['enhanced_extraction'] = true;

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     * @return array<string, mixed>
     */
    private function enhanceGolfCourseData(array $parsedData): array
    {
        // Apply golf course intelligence and data validation
        $enhanced = $parsedData;

        // Validate and enhance course ratings
        if (isset($enhanced['course_rating'])) {
            $enhanced['course_rating'] = $this->validateCourseRating($enhanced['course_rating']);
        }

        if (isset($enhanced['slope_rating'])) {
            $enhanced['slope_rating'] = $this->validateSlopeRating($enhanced['slope_rating']);
        }

        // Validate par values
        if (isset($enhanced['holes']) && is_array($enhanced['holes'])) {
            foreach ($enhanced['holes'] as &$hole) {
                if (isset($hole['par'])) {
                    $hole['par'] = $this->validatePar($hole['par']);
                }
                if (isset($hole['handicap'])) {
                    $hole['handicap'] = $this->validateHandicap($hole['handicap']);
                }
                if (isset($hole['number'])) {
                    $hole['front_nine'] = (int) $hole['number'] <= 9;
                }
            }
        }

        // Calculate totals if not provided
        if (isset($enhanced['holes']) && is_array($enhanced['holes'])) {
            $enhanced = $this->calculateCourseTotals($enhanced);
        }

        // Normalize tee names
        if (isset($enhanced['tee_name'])) {
            $enhanced['tee_name'] = $this->normalizeTeeNames($enhanced['tee_name']);
        }

        // Extract multiple yardages for different tees if available
        $enhanced['tee_yardages'] = $this->extractTeeYardages($enhanced);

        return $enhanced;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractGolfCourseProperties(array $data): array
    {
        return [
            // Basic Course Information
            'course_name' => $data['course_name'] ?? null,
            'course_location' => $data['course_location'] ?? null,
            'established_year' => $data['additional_info']['established'] ?? null,
            'designer' => $data['additional_info']['designer'] ?? null,
            'phone' => $data['additional_info']['phone'] ?? null,
            'website' => $data['additional_info']['website'] ?? null,
            'scorecard_type' => $data['additional_info']['scorecard_type'] ?? null,

            // Tee Information
            'tee_name' => $data['tee_name'] ?? null,
            'tee_colors' => $data['tee_colors'] ?? [],
            'tee_yardages' => $data['tee_yardages'] ?? [],

            // Course Ratings
            'course_rating' => $data['course_rating'] ?? null,
            'slope_rating' => $data['slope_rating'] ?? null,
            'total_par' => $data['total_par'] ?? $data['totals']['par'] ?? null,
            'total_yardage' => $data['total_yardage'] ?? $data['totals']['yardage'] ?? null,

            // Hole-by-Hole Data
            'hole_details' => $this->extractHoleDetails($data),
            'par_values' => $this->extractParValues($data),
            'handicap_values' => $this->extractHandicapValues($data),
            'yardage_values' => $this->extractYardageValues($data),

            // Nine-Hole Breakdowns
            'front_nine_par' => $data['front_nine']['par'] ?? null,
            'back_nine_par' => $data['back_nine']['par'] ?? null,
            'front_nine_yardage' => $data['front_nine']['yardage'] ?? null,
            'back_nine_yardage' => $data['back_nine']['yardage'] ?? null,

            // Round Data (if scorecard is filled out)
            'date_played' => $data['date'] ?? null,
            'players' => $data['players'] ?? [],
            'scores' => $this->extractPlayerScores($data),
            'tournament_info' => $data['additional_info']['tournament_name'] ?? null,
            'weather_conditions' => $data['additional_info']['weather'] ?? null,

            // Data Quality Metrics
            'data_completeness' => $this->calculateDataCompleteness($data),
            'confidence_score' => $this->calculateConfidenceScore($data),
            'missing_data_fields' => $this->identifyMissingData($data),
        ];
    }

    private function validateCourseRating(mixed $rating): ?float
    {
        if (is_numeric($rating)) {
            $numRating = (float) $rating;

            // Course ratings typically range from 67.0 to 77.0
            return ($numRating >= 60.0 && $numRating <= 85.0) ? $numRating : null;
        }

        return null;
    }

    private function validateSlopeRating(mixed $slope): ?int
    {
        if (is_numeric($slope)) {
            $numSlope = (int) $slope;

            // Slope ratings range from 55 to 155
            return ($numSlope >= 55 && $numSlope <= 155) ? $numSlope : null;
        }

        return null;
    }

    private function validatePar(mixed $par): ?int
    {
        if (is_numeric($par)) {
            $numPar = (int) $par;

            // Par values are typically 3, 4, or 5
            return in_array($numPar, [3, 4, 5]) ? $numPar : null;
        }

        return null;
    }

    private function validateHandicap(mixed $handicap): ?int
    {
        if (is_numeric($handicap)) {
            $numHandicap = (int) $handicap;

            // Handicap values range from 1 to 18
            return ($numHandicap >= 1 && $numHandicap <= 18) ? $numHandicap : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function calculateCourseTotals(array $data): array
    {
        if (! isset($data['holes']) || ! is_array($data['holes'])) {
            return $data;
        }

        $frontNinePar = 0;
        $backNinePar = 0;
        $frontNineYardage = 0;
        $backNineYardage = 0;

        foreach ($data['holes'] as $hole) {
            $holeNum = (int) ($hole['number'] ?? 0);
            $par = (int) ($hole['par'] ?? 0);
            $yardage = (int) ($hole['yardage'] ?? 0);

            if ($holeNum <= 9) {
                $frontNinePar += $par;
                $frontNineYardage += $yardage;
            } else {
                $backNinePar += $par;
                $backNineYardage += $yardage;
            }
        }

        // Set calculated totals if not already present
        if (empty($data['front_nine']['par'])) {
            $data['front_nine']['par'] = $frontNinePar;
        }
        if (empty($data['back_nine']['par'])) {
            $data['back_nine']['par'] = $backNinePar;
        }
        if (empty($data['front_nine']['yardage'])) {
            $data['front_nine']['yardage'] = $frontNineYardage;
        }
        if (empty($data['back_nine']['yardage'])) {
            $data['back_nine']['yardage'] = $backNineYardage;
        }

        // Calculate total par and yardage
        $data['totals']['par'] = $frontNinePar + $backNinePar;
        $data['totals']['yardage'] = $frontNineYardage + $backNineYardage;

        return $data;
    }

    private function normalizeTeeNames(string $teeName): string
    {
        $normalizedNames = [
            'championship' => 'Championship',
            'blue' => 'Blue',
            'white' => 'White',
            'red' => 'Red',
            'gold' => 'Gold',
            'black' => 'Black',
            'tips' => 'Tips',
            'pro' => 'Pro',
            'tournament' => 'Tournament',
            'mens' => 'Men\'s',
            'ladies' => 'Ladies',
            'senior' => 'Senior',
        ];

        $lowerName = strtolower(trim($teeName));

        return $normalizedNames[$lowerName] ?? ucwords($teeName);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function extractTeeYardages(array $data): array
    {
        $teeYardages = [];

        if (isset($data['holes']) && is_array($data['holes'])) {
            foreach ($data['holes'] as $hole) {
                if (isset($hole['yardage']) && is_numeric($hole['yardage'])) {
                    $teeYardages[] = (int) $hole['yardage'];
                }
            }
        }

        return $teeYardages;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function extractHoleDetails(array $data): array
    {
        if (! isset($data['holes']) || ! is_array($data['holes'])) {
            return [];
        }

        return array_map(function ($hole) {
            return [
                'number' => (int) ($hole['number'] ?? 0),
                'par' => $this->validatePar($hole['par'] ?? null),
                'handicap' => $this->validateHandicap($hole['handicap'] ?? null),
                'yardage' => is_numeric($hole['yardage'] ?? null) ? (int) $hole['yardage'] : null,
                'front_nine' => ((int) ($hole['number'] ?? 0)) <= 9,
            ];
        }, $data['holes']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function extractParValues(array $data): array
    {
        if (! isset($data['holes']) || ! is_array($data['holes'])) {
            return [];
        }

        $parValues = [];
        foreach ($data['holes'] as $hole) {
            $par = $this->validatePar($hole['par'] ?? null);
            $parValues[] = $par ?? 0;
        }

        return $parValues;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function extractHandicapValues(array $data): array
    {
        if (! isset($data['holes']) || ! is_array($data['holes'])) {
            return [];
        }

        $handicapValues = [];
        foreach ($data['holes'] as $hole) {
            $handicap = $this->validateHandicap($hole['handicap'] ?? null);
            $handicapValues[] = $handicap ?? 0;
        }

        return $handicapValues;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function extractYardageValues(array $data): array
    {
        if (! isset($data['holes']) || ! is_array($data['holes'])) {
            return [];
        }

        $yardageValues = [];
        foreach ($data['holes'] as $hole) {
            $yardage = is_numeric($hole['yardage'] ?? null) ? (int) $hole['yardage'] : 0;
            $yardageValues[] = $yardage;
        }

        return $yardageValues;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, int>>
     */
    private function extractPlayerScores(array $data): array
    {
        if (! isset($data['players']) || ! is_array($data['players'])) {
            return [];
        }

        $playerScores = [];
        foreach ($data['players'] as $index => $playerName) {
            $scores = [];

            if (isset($data['holes']) && is_array($data['holes'])) {
                foreach ($data['holes'] as $hole) {
                    if (isset($hole['scores'][$index]) && is_numeric($hole['scores'][$index])) {
                        $scores[] = (int) $hole['scores'][$index];
                    } else {
                        $scores[] = 0;
                    }
                }
            }

            $playerScores[$playerName] = $scores;
        }

        return $playerScores;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculateDataCompleteness(array $data): float
    {
        $requiredFields = [
            'course_name', 'tee_name', 'course_rating', 'slope_rating',
            'total_par', 'total_yardage', 'holes',
        ];

        $presentFields = 0;
        foreach ($requiredFields as $field) {
            if (! empty($data[$field])) {
                $presentFields++;
            }
        }

        // Check hole data completeness
        if (isset($data['holes']) && is_array($data['holes']) && count($data['holes']) === 18) {
            $holeDataComplete = true;
            foreach ($data['holes'] as $hole) {
                if (empty($hole['par']) || empty($hole['handicap'])) {
                    $holeDataComplete = false;
                    break;
                }
            }
            if ($holeDataComplete) {
                $presentFields += 2; // Bonus for complete hole data
            }
        }

        return round($presentFields / (count($requiredFields) + 2), 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calculateConfidenceScore(array $data): float
    {
        $confidence = 0.8; // Base confidence

        // Boost confidence for complete course information
        if (! empty($data['course_name'])) {
            $confidence += 0.05;
        }
        if (! empty($data['course_rating']) && ! empty($data['slope_rating'])) {
            $confidence += 0.05;
        }
        if (isset($data['holes']) && count($data['holes']) === 18) {
            $confidence += 0.1;
        }

        return min($confidence, 1.0);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function identifyMissingData(array $data): array
    {
        $missing = [];

        if (empty($data['course_name'])) {
            $missing[] = 'course_name';
        }
        if (empty($data['course_location'])) {
            $missing[] = 'course_location';
        }
        if (empty($data['tee_name'])) {
            $missing[] = 'tee_name';
        }
        if (empty($data['course_rating'])) {
            $missing[] = 'course_rating';
        }
        if (empty($data['slope_rating'])) {
            $missing[] = 'slope_rating';
        }
        if (! isset($data['holes']) || count($data['holes']) < 18) {
            $missing[] = 'complete_hole_data';
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    private function getMockOcrData(): array
    {
        $mockConfidence = (float) ($this->providerConfig['confidence'] ?? 0.95);
        $mockText = "PEBBLE BEACH GOLF LINKS\nPebble Beach, CA 93953\nChampionship Tees\nDate: 07/24/2024\nPlayer 1: John Doe\nPlayer 2: Jane Smith\nHole  Par  Hdcp  Yds  P1  P2\n1     4    10    381  4   5\n2     4    16    502  5   4\n3     4    4     390  3   4\n4     4    2     327  4   5\n5     3    14    188  3   2\n6     5    12    513  6   5\n7     3    8     106  4   3\n8     4    18    418  4   4\n9     4    6     464  5   4\nOUT   35         3289 38  36\n10    4    11    446  4   4\n11    4    15    384  5   5\n12    3    17    202  3   3\n13    4    1     399  4   5\n14    5    3     573  5   6\n15    4    13    397  4   4\n16    4    9     402  3   4\n17    3    7     178  3   2\n18    4    5     543  4   4\nIN    35         3524 35  37\nTOTAL 70         6813 73  73\nSlope: 113  Rating: 72.1\nDesigned by: Jack Nicklaus & Robert Trent Jones Sr.\nEstablished: 1919";

        // Create comprehensive structured mock data
        $mockStructuredData = [
            'course_name' => 'Pebble Beach Golf Links',
            'course_location' => 'Pebble Beach, CA 93953',
            'date' => '2024-07-24',
            'tee_name' => 'Championship',
            'tee_colors' => ['Black'],
            'course_rating' => 72.1,
            'slope_rating' => 113,
            'total_par' => 70,
            'total_yardage' => 6813,
            'players' => ['John Doe', 'Jane Smith'],
            'holes' => [
                ['number' => 1, 'par' => 4, 'handicap' => 10, 'yardage' => 381, 'front_nine' => true, 'scores' => [4, 5]],
                ['number' => 2, 'par' => 4, 'handicap' => 16, 'yardage' => 502, 'front_nine' => true, 'scores' => [5, 4]],
                ['number' => 3, 'par' => 4, 'handicap' => 4, 'yardage' => 390, 'front_nine' => true, 'scores' => [3, 4]],
                ['number' => 4, 'par' => 4, 'handicap' => 2, 'yardage' => 327, 'front_nine' => true, 'scores' => [4, 5]],
                ['number' => 5, 'par' => 3, 'handicap' => 14, 'yardage' => 188, 'front_nine' => true, 'scores' => [3, 2]],
                ['number' => 6, 'par' => 5, 'handicap' => 12, 'yardage' => 513, 'front_nine' => true, 'scores' => [6, 5]],
                ['number' => 7, 'par' => 3, 'handicap' => 8, 'yardage' => 106, 'front_nine' => true, 'scores' => [4, 3]],
                ['number' => 8, 'par' => 4, 'handicap' => 18, 'yardage' => 418, 'front_nine' => true, 'scores' => [4, 4]],
                ['number' => 9, 'par' => 4, 'handicap' => 6, 'yardage' => 464, 'front_nine' => true, 'scores' => [5, 4]],
                ['number' => 10, 'par' => 4, 'handicap' => 11, 'yardage' => 446, 'front_nine' => false, 'scores' => [4, 4]],
                ['number' => 11, 'par' => 4, 'handicap' => 15, 'yardage' => 384, 'front_nine' => false, 'scores' => [5, 5]],
                ['number' => 12, 'par' => 3, 'handicap' => 17, 'yardage' => 202, 'front_nine' => false, 'scores' => [3, 3]],
                ['number' => 13, 'par' => 4, 'handicap' => 1, 'yardage' => 399, 'front_nine' => false, 'scores' => [4, 5]],
                ['number' => 14, 'par' => 5, 'handicap' => 3, 'yardage' => 573, 'front_nine' => false, 'scores' => [5, 6]],
                ['number' => 15, 'par' => 4, 'handicap' => 13, 'yardage' => 397, 'front_nine' => false, 'scores' => [4, 4]],
                ['number' => 16, 'par' => 4, 'handicap' => 9, 'yardage' => 402, 'front_nine' => false, 'scores' => [3, 4]],
                ['number' => 17, 'par' => 3, 'handicap' => 7, 'yardage' => 178, 'front_nine' => false, 'scores' => [3, 2]],
                ['number' => 18, 'par' => 4, 'handicap' => 5, 'yardage' => 543, 'front_nine' => false, 'scores' => [4, 4]],
            ],
            'front_nine' => ['par' => 35, 'yardage' => 3289, 'scores' => [38, 36]],
            'back_nine' => ['par' => 35, 'yardage' => 3524, 'scores' => [35, 37]],
            'totals' => ['par' => 70, 'yardage' => 6813, 'scores' => [73, 73]],
            'additional_info' => [
                'designer' => 'Jack Nicklaus & Robert Trent Jones Sr.',
                'established' => '1919',
                'scorecard_type' => 'resort',
            ],
        ];

        // Apply the same enhancements as OpenAI data
        $enhancedData = $this->enhanceGolfCourseData($mockStructuredData);

        return [
            'text' => $mockText,
            'raw_text' => $mockText,
            'confidence' => (int) ($mockConfidence * 100),
            'provider' => 'mock',
            'structured_data' => $enhancedData,
            'golf_course_properties' => $this->extractGolfCourseProperties($enhancedData),
            'words' => [
                ['text' => 'PEBBLE', 'confidence' => 0.95, 'bbox' => 100],
                ['text' => 'BEACH', 'confidence' => 0.93, 'bbox' => 150],
                ['text' => 'GOLF', 'confidence' => 0.94, 'bbox' => 200],
                ['text' => 'LINKS', 'confidence' => 0.91, 'bbox' => 250],
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
            ],
        ];
    }
}
