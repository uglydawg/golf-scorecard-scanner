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
                                    'text' => $this->getGolfScorecardExtractionPrompt(),
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
                                    'text' => $this->getGolfScorecardExtractionPrompt(),
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
        return 'This is a golf scorecard image. Extract ALL information and format as structured JSON:

{
  "course_name": "Full name of the golf course",
  "course_location": "City, State, Country or address if visible",
  "date": "Date of play if visible (format: YYYY-MM-DD or MM/DD/YYYY)",
  "tee_name": "Tee box name (e.g., Championship, Blue, White, Red, Gold)",
  "tee_colors": ["Colors of tee boxes if multiple shown"],
  "course_rating": "Course rating number (e.g., 72.1)",
  "slope_rating": "Slope rating number (e.g., 113)",
  "total_par": "Total par for the course (usually 70-72)",
  "total_yardage": "Total yardage for the course",
  "players": ["List of all player names"],
  "holes": [
    {
      "number": 1,
      "par": 4,
      "handicap": 10,
      "yardage": 350,
      "front_nine": true,
      "scores": [4, 5]
    }
  ],
  "front_nine": {
    "par": 36,
    "yardage": 3200,
    "scores": [38, 36]
  },
  "back_nine": {
    "par": 36,
    "yardage": 3400,
    "scores": [35, 37]
  },
  "totals": {
    "par": 72,
    "yardage": 6600,
    "scores": [73, 73]
  },
  "additional_info": {
    "tournament_name": "Tournament or event name if visible",
    "weather": "Weather conditions if noted",
    "designer": "Course designer if mentioned",
    "established": "Year established if shown",
    "phone": "Phone number if visible",
    "website": "Website if visible",
    "scorecard_type": "Type of scorecard (e.g., daily fee, private, resort)"
  }
}

CRITICAL EXTRACTION REQUIREMENTS:
1. COURSE IDENTIFICATION:
   - Look for large text at top (course name)
   - Search for city/state/address information
   - Identify tee box names (Championship, Blue, White, etc.)
   - Find course designer or architect credits

2. TEE INFORMATION:
   - Identify different tee boxes by color/name
   - Extract yardages for each tee (may have multiple columns)
   - Find course rating and slope for the specific tee
   - Look for total yardage at bottom

3. HOLE DATA (CRITICAL):
   - Extract hole numbers 1-18
   - Par values for each hole (3, 4, or 5)
   - Handicap rankings (1-18, difficulty order)
   - Yardages from the scorecard tee
   - Distinguish front 9 (holes 1-9) from back 9 (holes 10-18)

4. SCORING DATA:
   - Player names from header
   - Individual hole scores for each player
   - Front nine subtotals (OUT)
   - Back nine subtotals (IN)
   - Total scores for each player

5. COURSE RATINGS:
   - Course rating (difficulty for scratch golfer)
   - Slope rating (relative difficulty, 55-155 scale)
   - Total par and yardage

6. ADDITIONAL DETAILS:
   - Date of play
   - Tournament or event information
   - Weather conditions
   - Special notes or rules

Extract every visible number, name, and piece of text. Be extremely thorough and accurate with numbers as they are critical for golf handicap calculations.';
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
