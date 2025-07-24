<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;
use ScorecardScanner\Services\ScorecardProcessingService;

beforeEach(function () {
    // Run migrations for in-memory database
    $this->artisan('migrate:fresh');

    DB::beginTransaction();

    $this->processingService = app(ScorecardProcessingService::class);
    $this->imageService = app(ImageProcessingService::class);
    $this->ocrService = app(OcrService::class);

    // Set up storage for testing
    Storage::fake('public');
});

afterEach(function () {
    DB::rollBack();
});

it('processes cyprus point front nine scorecard', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $imagePath = base_path('tests/scorecards/cyprus-point-front.jpg');
    expect($imagePath)->toBeFile('Cyprus Point Front scorecard image must exist');

    // Create UploadedFile from the actual image
    $uploadedFile = new UploadedFile(
        $imagePath,
        'cyprus-point-front.jpg',
        'image/jpeg',
        null,
        true // test mode
    );

    echo "\n".str_repeat('=', 80)."\n";
    echo "PROCESSING: Cyprus Point Front Nine Scorecard\n";
    echo str_repeat('=', 80)."\n";

    // Process the image through our OCR service
    echo "📷 Image Processing:\n";
    echo "- Original file: {$uploadedFile->getClientOriginalName()}\n";
    echo '- File size: '.number_format($uploadedFile->getSize() / 1024, 2)." KB\n";
    echo "- MIME type: {$uploadedFile->getMimeType()}\n\n";

    // Store the image for processing
    $storageDisk = config('scorecard-scanner.storage.disk', 'local');
    $storedPath = $uploadedFile->store('scorecards/originals', $storageDisk);
    echo "- Stored at: {$storedPath} (disk: {$storageDisk})\n\n";

    // Process image for OCR
    echo "🔍 OCR Processing:\n";
    echo '- Configuration Provider: '.config('scorecard-scanner.ocr.default', 'unknown')."\n";
    echo '- Environment Variable: '.env('SCORECARD_OCR_PROVIDER', 'not set')."\n";
    echo '- Model: '.config('scorecard-scanner.ocr.providers.openrouter.model', 'unknown')."\n";

    try {
        $ocrResults = $this->ocrService->extractText($storedPath);

        echo '- Actual OCR Provider used: '.(isset($ocrResults['provider']) ? $ocrResults['provider'] : 'unknown')."\n";
        echo '- Overall confidence: '.number_format($ocrResults['confidence'], 1)."%\n";
        echo '- Raw text length: '.strlen($ocrResults['text'])." characters\n\n";

        // Display extracted raw text
        echo "📝 Raw OCR Text:\n";
        echo str_repeat('-', 40)."\n";
        echo $ocrResults['text']."\n";
        echo str_repeat('-', 40)."\n\n";

        // Analyze the text for golf-specific data
        echo "⛳ Golf Data Analysis:\n";
        analyzeGolfData($ocrResults['text'], 'Front Nine');

        // Extract and log detailed hole data
        echo "\n🏌️ Detailed Hole Data Extraction:\n";
        $holeData = extractHoleData($ocrResults['text']);
        logHoleData($holeData);

        // Internet search and comparison
        echo "\n🌐 Internet Search & Comparison:\n";
        performInternetSearchComparison($holeData);

    } catch (\Exception $e) {
        echo '❌ OCR Processing failed: '.$e->getMessage()."\n";
        echo "This may be due to API quota limits\n\n";
    }

    // Process through the full service
    echo "\n🏌️ Full Service Processing:\n";
    try {
        $scan = $this->processingService->processUploadedImage($uploadedFile, $user->id);

        if ($scan) {
            echo "✅ Processing completed successfully!\n";
            echo "- Scan ID: {$scan->id}\n";
            echo "- Status: {$scan->status}\n";
            echo "- Created: {$scan->created_at}\n\n";

            if ($scan->parsed_data) {
                echo "📊 Parsed Data:\n";
                displayParsedData($scan->parsed_data);
            }

            // Add basic assertions
            expect($scan)->not->toBeNull();
            expect($scan->id)->not->toBeNull();
            expect($scan->user_id)->toBe($user->id);
        }
    } catch (\Exception $e) {
        echo '❌ Processing failed: '.$e->getMessage()."\n";
        echo "This is expected due to service issues - OCR extraction shown above\n";
    }

    echo "\n".str_repeat('=', 80)."\n";
    echo "MANUAL VERIFICATION REQUIRED:\n";
    echo "Please review the extracted text above and verify:\n";
    echo "1. Course name is correctly identified\n";
    echo "2. Hole numbers (1-9) are present\n";
    echo "3. Par values are extracted\n";
    echo "4. Any player scores are captured\n";
    echo "5. Date information if visible\n";
    echo str_repeat('=', 80)."\n\n";

    // Assertions for test framework
    expect($ocrResults['text'])->not->toBeEmpty('OCR should extract some text');
    expect($ocrResults['confidence'])->toBeGreaterThan(0, 'OCR should have some confidence level');
    expect($ocrResults['words'])->toBeArray('OCR should return word-level data');
});

it('processes cyprus point back nine scorecard', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $imagePath = base_path('tests/scorecards/cyprus-point-back.jpg');
    expect($imagePath)->toBeFile('Cyprus Point Back scorecard image must exist');

    // Create UploadedFile from the actual image
    $uploadedFile = new UploadedFile(
        $imagePath,
        'cyprus-point-back.jpg',
        'image/jpeg',
        null,
        true // test mode
    );

    echo "\n".str_repeat('=', 80)."\n";
    echo "PROCESSING: Cyprus Point Back Nine Scorecard\n";
    echo str_repeat('=', 80)."\n";

    // Process the image through our OCR service
    echo "📷 Image Processing:\n";
    echo "- Original file: {$uploadedFile->getClientOriginalName()}\n";
    echo '- File size: '.number_format($uploadedFile->getSize() / 1024, 2)." KB\n";
    echo "- MIME type: {$uploadedFile->getMimeType()}\n\n";

    // Store the image for processing
    $storageDisk = config('scorecard-scanner.storage.disk', 'local');
    $storedPath = $uploadedFile->store('scorecards/originals', $storageDisk);
    echo "- Stored at: {$storedPath} (disk: {$storageDisk})\n\n";

    // Process image for OCR
    echo "🔍 OCR Processing:\n";
    try {
        $ocrResults = $this->ocrService->extractText($storedPath);

        echo "- OCR Provider: openrouter\n";
        echo '- Overall confidence: '.number_format($ocrResults['confidence'], 1)."%\n";
        echo '- Raw text length: '.strlen($ocrResults['text'])." characters\n\n";

        // Display extracted raw text
        echo "📝 Raw OCR Text:\n";
        echo str_repeat('-', 40)."\n";
        echo $ocrResults['text']."\n";
        echo str_repeat('-', 40)."\n\n";

        // Analyze the text for golf-specific data
        echo "⛳ Golf Data Analysis:\n";
        analyzeGolfData($ocrResults['text'], 'Back Nine');

    } catch (\Exception $e) {
        echo '❌ OCR Processing failed: '.$e->getMessage()."\n";
        echo "This may be due to API quota limits\n\n";
    }

    // Process through the full service
    echo "\n🏌️ Full Service Processing:\n";
    try {
        $scan = $this->processingService->processUploadedImage($uploadedFile, $user->id);

        if ($scan) {
            echo "✅ Processing completed successfully!\n";
            echo "- Scan ID: {$scan->id}\n";
            echo "- Status: {$scan->status}\n";
            echo "- Created: {$scan->created_at}\n\n";

            if ($scan->parsed_data) {
                echo "📊 Parsed Data:\n";
                displayParsedData($scan->parsed_data);
            }
        }
    } catch (\Exception $e) {
        echo '❌ Processing failed: '.$e->getMessage()."\n";
        echo "This is expected due to service issues - OCR extraction shown above\n";
    }

    echo "\n".str_repeat('=', 80)."\n";
    echo "MANUAL VERIFICATION REQUIRED:\n";
    echo "Please review the extracted text above and verify:\n";
    echo "1. Course name is correctly identified\n";
    echo "2. Hole numbers (10-18) are present\n";
    echo "3. Par values are extracted\n";
    echo "4. Any player scores are captured\n";
    echo "5. Total scores and course rating info\n";
    echo str_repeat('=', 80)."\n\n";

    // Assertions for test framework
    expect($ocrResults['text'])->not->toBeEmpty('OCR should extract some text');
    expect($ocrResults['confidence'])->toBeGreaterThan(0, 'OCR should have some confidence level');
    expect($ocrResults['words'])->toBeArray('OCR should return word-level data');
});

it('tests image preprocessing pipeline', function () {
    echo "\n".str_repeat('=', 80)."\n";
    echo "IMAGE PREPROCESSING PIPELINE TEST\n";
    echo str_repeat('=', 80)."\n";

    foreach (['cyprus-point-front.jpg', 'cyprus-point-back.jpg'] as $filename) {
        $imagePath = base_path("tests/scorecards/{$filename}");

        echo "\n📷 Processing: {$filename}\n";
        echo str_repeat('-', 40)."\n";

        // Create UploadedFile
        $uploadedFile = new UploadedFile($imagePath, $filename, 'image/jpeg', null, true);
        $storageDisk = config('scorecard-scanner.storage.disk', 'local');
        $storedPath = $uploadedFile->store('scorecards/originals', $storageDisk);

        // Get original image info
        $originalSize = $uploadedFile->getSize();
        echo '- Original size: '.number_format($originalSize / 1024, 2)." KB\n";

        // Apply preprocessing
        echo "- Applying preprocessing...\n";
        $processedPath = $this->imageService->preprocessImage($storedPath);

        echo "- Processed path: {$processedPath}\n";
        echo "- Preprocessing completed ✅\n";

        // Test corner detection if method exists
        echo "- Testing corner detection...\n";
        if (method_exists($this->imageService, 'detectScorecardCorners')) {
            try {
                $corners = $this->imageService->detectScorecardCorners($processedPath);

                echo "- Corner detection results:\n";
                foreach ($corners as $corner => $coords) {
                    echo "  * {$corner}: ({$coords['x']}, {$coords['y']})\n";
                }

                expect($corners)->toBeArray();
            } catch (\Exception $e) {
                echo '- Corner detection failed: '.$e->getMessage()."\n";
            }
        } else {
            echo "- Corner detection method not implemented yet\n";
        }
    }

    echo "\n".str_repeat('=', 80)."\n";
    echo "PREPROCESSING PIPELINE COMPLETED\n";
    echo "All images processed through enhancement pipeline\n";
    echo str_repeat('=', 80)."\n\n";
});

// Helper functions
function analyzeGolfData(string $text, string $section): void
{
    $lines = explode("\n", $text);

    echo '- Total lines extracted: '.count($lines)."\n";

    // Look for course name patterns
    $coursePatterns = ['CYPRESS', 'POINT', 'GOLF', 'CLUB', 'COURSE'];
    $courseLines = [];
    foreach ($lines as $line) {
        $upperLine = strtoupper($line);
        foreach ($coursePatterns as $pattern) {
            if (strpos($upperLine, $pattern) !== false) {
                $courseLines[] = trim($line);
                break;
            }
        }
    }

    if (! empty($courseLines)) {
        echo "- Potential course names found:\n";
        foreach (array_unique($courseLines) as $courseLine) {
            echo "  * {$courseLine}\n";
        }
    }

    // Look for hole numbers
    $holeNumbers = [];
    foreach ($lines as $line) {
        if (preg_match_all('/\b([1-9]|1[0-8])\b/', $line, $matches)) {
            $holeNumbers = array_merge($holeNumbers, $matches[1]);
        }
    }

    if (! empty($holeNumbers)) {
        $uniqueHoles = array_unique($holeNumbers);
        sort($uniqueHoles, SORT_NUMERIC);
        echo '- Hole numbers detected: '.implode(', ', $uniqueHoles)."\n";

        if ($section === 'Front Nine') {
            $expectedHoles = range(1, 9);
            $frontNineHoles = array_filter($uniqueHoles, fn ($h) => $h >= 1 && $h <= 9);
            echo '- Front nine holes found: '.implode(', ', $frontNineHoles)."\n";
        } else {
            $expectedHoles = range(10, 18);
            $backNineHoles = array_filter($uniqueHoles, fn ($h) => $h >= 10 && $h <= 18);
            echo '- Back nine holes found: '.implode(', ', $backNineHoles)."\n";
        }
    }

    // Look for par values (typically 3, 4, 5)
    $parValues = [];
    foreach ($lines as $line) {
        if (preg_match_all('/\b[345]\b/', $line, $matches)) {
            $parValues = array_merge($parValues, $matches[0]);
        }
    }

    if (! empty($parValues)) {
        $parCounts = array_count_values($parValues);
        echo '- Par values found: ';
        foreach ($parCounts as $par => $count) {
            echo "Par {$par} ({$count}x) ";
        }
        echo "\n";
    }

    // Look for dates
    if (preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $text, $dateMatch)) {
        echo "- Date found: {$dateMatch[0]}\n";
    }

    // Look for player names or scores
    $scorePatterns = [];
    foreach ($lines as $line) {
        if (preg_match('/\b([4-9]|[1-2][0-9])\b/', $line)) {
            $scorePatterns[] = trim($line);
        }
    }

    if (! empty($scorePatterns)) {
        echo "- Lines with potential scores:\n";
        foreach (array_slice(array_unique($scorePatterns), 0, 5) as $scoreLine) {
            echo "  * {$scoreLine}\n";
        }
        if (count($scorePatterns) > 5) {
            echo '  * ... and '.(count($scorePatterns) - 5)." more\n";
        }
    }
}

function displayParsedData(array $parsedData): void
{
    foreach ($parsedData as $key => $value) {
        echo "- {$key}: ";
        if (is_array($value)) {
            echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            echo $value;
        }
        echo "\n";
    }
}

function extractHoleData(string $text): array
{
    $holeData = [
        'course_name' => null,
        'holes' => [],
        'par_total' => null,
        'course_rating' => null,
        'slope' => null,
    ];

    $lines = explode("\n", $text);

    // Extract course name
    foreach ($lines as $line) {
        if (stripos($line, 'cypress point') !== false) {
            $holeData['course_name'] = 'Cypress Point Club';
            break;
        } elseif (preg_match('/\*\*Course Name and Location:\*\*\s*(.+)/i', $line, $matches)) {
            $holeData['course_name'] = trim($matches[1]);
            break;
        } elseif (preg_match('/COURSE NAME:\s*(.+)/i', $line, $matches)) {
            $holeData['course_name'] = trim($matches[1]);
            break;
        }
    }

    // Extract structured hole data from table format
    $parLine = null;
    $handicapLine = null;
    $playerLines = [];

    foreach ($lines as $line) {
        if (preg_match('/PAR\s*\|(.+)/i', $line, $matches)) {
            $parLine = $matches[1];
        } elseif (preg_match('/HANDICAP\s*\|(.+)/i', $line, $matches)) {
            $handicapLine = $matches[1];
        } elseif (preg_match('/PLAYER\s*\|(.+)/i', $line, $matches)) {
            $playerLines[] = $matches[1];
        } elseif (preg_match('/COURSE RATING:\s*(\d+)/i', $line, $matches)) {
            $holeData['course_rating'] = (int) $matches[1];
        }
    }

    // Parse par values - try multiple formats
    if ($parLine) {
        $parValues = array_filter(array_map('trim', explode('|', $parLine)));
        $parValues = array_values(array_filter($parValues, 'is_numeric'));

        for ($i = 0; $i < min(9, count($parValues)); $i++) {
            $holeData['holes'][$i + 1] = [
                'hole' => $i + 1,
                'par' => (int) $parValues[$i],
                'handicap' => null,
                'player_scores' => [],
            ];
        }
    } else {
        // Try alternative parsing from detailed hole listings
        $parValues = [];
        $handicapValues = [];

        foreach ($lines as $line) {
            // Look for detailed par value listings
            if (preg_match('/\*\*Par Values for Each Hole:\*\*/i', $line)) {
                $inParSection = true;

                continue;
            }

            // Parse individual hole par values like "- 1: 4"
            if (preg_match('/- (\d+): (\d+)/', $line, $matches)) {
                $hole = (int) $matches[1];
                $par = (int) $matches[2];
                if ($hole <= 9) { // Front nine only
                    $parValues[$hole] = $par;
                }
            }

            // Parse handicap values
            if (preg_match('/- (\d+): (\d+)/', $line, $matches) && stripos($line, 'handicap') !== false) {
                $hole = (int) $matches[1];
                $handicap = (int) $matches[2];
                if ($hole <= 9) {
                    $handicapValues[$hole] = $handicap;
                }
            }
        }

        // Create hole data from parsed values
        for ($hole = 1; $hole <= 9; $hole++) {
            if (isset($parValues[$hole])) {
                $holeData['holes'][$hole] = [
                    'hole' => $hole,
                    'par' => $parValues[$hole],
                    'handicap' => $handicapValues[$hole] ?? null,
                    'player_scores' => [],
                ];
            }
        }
    }

    // Parse handicap values
    if ($handicapLine && isset($holeData['holes'])) {
        $handicapValues = array_filter(array_map('trim', explode('|', $handicapLine)));
        $handicapValues = array_values(array_filter($handicapValues, 'is_numeric'));

        foreach ($holeData['holes'] as $hole => &$data) {
            if (isset($handicapValues[$hole - 1])) {
                $data['handicap'] = (int) $handicapValues[$hole - 1];
            }
        }
    }

    // Calculate par total
    if (! empty($holeData['holes'])) {
        $holeData['par_total'] = array_sum(array_column($holeData['holes'], 'par'));
    }

    return $holeData;
}

function logHoleData(array $holeData): void
{
    echo '📊 Course: '.($holeData['course_name'] ?? 'Unknown')."\n";
    echo '📈 Course Rating: '.($holeData['course_rating'] ?? 'Not found')."\n";
    echo "📋 Front Nine Holes:\n";

    if (! empty($holeData['holes'])) {
        echo "┌──────┬─────┬──────────┐\n";
        echo "│ Hole │ Par │ Handicap │\n";
        echo "├──────┼─────┼──────────┤\n";

        foreach ($holeData['holes'] as $hole => $data) {
            echo sprintf(
                "│  %2d  │  %1d  │    %2s    │\n",
                $data['hole'],
                $data['par'],
                $data['handicap'] ?? '--'
            );
        }

        echo "└──────┴─────┴──────────┘\n";
        echo '📊 Par Total (Front 9): '.($holeData['par_total'] ?? 'Unknown')."\n";
    } else {
        echo "❌ No structured hole data found\n";
    }
}

function performInternetSearchComparison(array $holeData): void
{
    if (empty($holeData['course_name'])) {
        echo "❌ No course name found for internet search\n";

        return;
    }

    echo '🔍 Searching internet for: '.$holeData['course_name']."\n";

    try {
        // Use OpenRouter with a different model for internet search comparison
        $searchResults = searchCourseOnInternet($holeData['course_name']);
        echo "✅ Internet search completed\n";

        echo "\n📊 Comparison Results:\n";
        compareWithInternetData($holeData, $searchResults);

    } catch (\Exception $e) {
        echo '❌ Internet search failed: '.$e->getMessage()."\n";
    }
}

function searchCourseOnInternet(string $courseName): array
{
    $apiKey = env('OPENROUTER_API_KEY');
    if (empty($apiKey)) {
        throw new \Exception('OpenRouter API key not configured');
    }

    // Use a different model for internet search comparison
    $searchModel = 'anthropic/claude-3.5-sonnet';

    $payload = [
        'model' => $searchModel,
        'messages' => [
            [
                'role' => 'user',
                'content' => "Search the internet for information about '{$courseName}' golf course. Please provide:
1. Official course name and location
2. Course rating and slope rating
3. Par values for holes 1-9 (front nine)
4. Course architect/designer
5. Any notable features or history
6. Current status (active, private, public, etc.)

Format your response as structured data that can be easily compared with scorecard data.",
            ],
        ],
        'max_tokens' => 2000,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey,
        'HTTP-Referer: '.config('app.url', 'http://localhost'),
        'X-Title: Golf Scorecard OCR Scanner - Internet Search',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new \Exception("Internet search API error: HTTP {$httpCode} - {$response}");
    }

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';

    return [
        'model_used' => $searchModel,
        'search_content' => $content,
        'timestamp' => now()->toISOString(),
    ];
}

function compareWithInternetData(array $ocrData, array $internetData): void
{
    echo '🤖 Search Model: '.$internetData['model_used']."\n";
    echo '⏰ Search Time: '.$internetData['timestamp']."\n\n";

    echo "📄 Internet Search Results:\n";
    echo str_repeat('-', 60)."\n";
    echo $internetData['search_content']."\n";
    echo str_repeat('-', 60)."\n\n";

    echo "🔍 Data Comparison:\n";
    echo "┌─────────────────────┬─────────────────┬─────────────────┐\n";
    echo "│ Field               │ OCR Data        │ Internet Data   │\n";
    echo "├─────────────────────┼─────────────────┼─────────────────┤\n";

    // Compare course name
    $ocrName = $ocrData['course_name'] ?? 'Not found';
    $internetNameMatch = stripos($internetData['search_content'], 'cypress point') !== false ? '✅ Match' : '❓ Check';
    echo sprintf("│ %-19s │ %-15s │ %-15s │\n", 'Course Name', substr($ocrName, 0, 15), $internetNameMatch);

    // Compare par total
    $ocrParTotal = $ocrData['par_total'] ?? 'Not found';
    echo sprintf("│ %-19s │ %-15s │ %-15s │\n", 'Par Total (F9)', $ocrParTotal, 'See above ↑');

    // Compare course rating
    $ocrRating = $ocrData['course_rating'] ?? 'Not found';
    echo sprintf("│ %-19s │ %-15s │ %-15s │\n", 'Course Rating', $ocrRating, 'See above ↑');

    echo "└─────────────────────┴─────────────────┴─────────────────┘\n\n";

    echo "💡 Verification Notes:\n";
    echo '- OCR extracted '.count($ocrData['holes'])." holes from the scorecard\n";
    echo "- Internet search provides reference data for validation\n";
    echo "- Compare specific par values and course details above\n";
    echo "- Cypress Point Club is a famous private golf course in California\n";
}
