<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;

uses(Tests\TestCase::class);

$ocrService = null;
$imageService = null;

beforeEach(function () use (&$ocrService, &$imageService) {
    $ocrService = app(OcrService::class);
    $imageService = app(ImageProcessingService::class);
    
    $this->ocrService = $ocrService;
    $this->imageService = $imageService;

    // Set up storage for testing
    Storage::fake('local');
});

it('extracts text from cyprus point front nine scorecard', function () {
    $imagePath = base_path('tests/scorecards/cyprus-point-front.jpg');
    expect($imagePath)->toBeFile('Cyprus Point Front scorecard image must exist');

    echo "\n".str_repeat('=', 80)."\n";
    echo "🏌️ CYPRUS POINT FRONT NINE - OCR ANALYSIS\n";
    echo str_repeat('=', 80)."\n";

    // Create a temporary file in our fake storage
    $imageContent = file_get_contents($imagePath);
    $storedPath = 'scorecards/cyprus-point-front.jpg';
    Storage::disk('local')->put($storedPath, $imageContent);

    echo "📷 Image Information:\n";
    echo "- File: cyprus-point-front.jpg\n";
    echo '- Size: '.number_format(strlen($imageContent) / 1024, 2)." KB\n";
    echo "- Stored at: {$storedPath}\n\n";

    // Process with OCR
    echo "🔍 OCR Processing:\n";
    $ocrResults = $this->ocrService->extractText($storedPath);

    echo '- OCR Provider: '.config('scorecard-scanner.ocr.default', 'mock')."\n";
    echo '- Overall Confidence: '.number_format($ocrResults['confidence'] * 100, 1)."%\n";
    echo '- Words Extracted: '.count($ocrResults['words'])."\n";
    echo '- Lines Processed: '.count($ocrResults['lines'])."\n\n";

    echo "📝 Raw OCR Text:\n";
    echo str_repeat('-', 60)."\n";
    echo $ocrResults['raw_text']."\n";
    echo str_repeat('-', 60)."\n\n";

    // Analyze for golf-specific data
    echo "⛳ Golf Data Analysis:\n";
    analyzeGolfData($ocrResults['raw_text'], 'Front Nine (Holes 1-9)');

    echo "\n".str_repeat('=', 80)."\n";
    echo "✅ MANUAL VERIFICATION CHECKLIST - FRONT NINE:\n";
    echo str_repeat('=', 80)."\n";
    echo "Please verify the following data was extracted correctly:\n\n";
    echo "□ Course Name: Cyprus Point mentioned?\n";
    echo "□ Hole Numbers: Should see holes 1-9\n";
    echo "□ Par Values: Typically 3, 4, or 5 for each hole\n";
    echo "□ Yardages: Distance numbers for each hole (if visible)\n";
    echo "□ Player Names: Any player names on the scorecard\n";
    echo "□ Scores: Individual hole scores (if filled in)\n";
    echo "□ Date: Date the round was played (if visible)\n";
    echo "□ Front Nine Total: Sum of holes 1-9 (if calculated)\n\n";
    echo str_repeat('=', 80)."\n\n";

    // Assertions
    expect($ocrResults['raw_text'])->not->toBeEmpty('OCR should extract text from the image');
    expect($ocrResults['confidence'])->toBeGreaterThan(0, 'OCR should have some confidence level');
    expect($ocrResults['words'])->toBeArray('OCR should return word-level data');
    expect($ocrResults['lines'])->toBeArray('OCR should return line-level data');
});

it('extracts text from cyprus point back nine scorecard', function () {
    $imagePath = base_path('tests/scorecards/cyprus-point-back.jpg');
    expect($imagePath)->toBeFile('Cyprus Point Back scorecard image must exist');

    echo "\n".str_repeat('=', 80)."\n";
    echo "🏌️ CYPRUS POINT BACK NINE - OCR ANALYSIS\n";
    echo str_repeat('=', 80)."\n";

    // Create a temporary file in our fake storage
    $imageContent = file_get_contents($imagePath);
    $storedPath = 'scorecards/cyprus-point-back.jpg';
    Storage::disk('local')->put($storedPath, $imageContent);

    echo "📷 Image Information:\n";
    echo "- File: cyprus-point-back.jpg\n";
    echo '- Size: '.number_format(strlen($imageContent) / 1024, 2)." KB\n";
    echo "- Stored at: {$storedPath}\n\n";

    // Process with OCR
    echo "🔍 OCR Processing:\n";
    $ocrResults = $this->ocrService->extractText($storedPath);

    echo '- OCR Provider: '.config('scorecard-scanner.ocr.default', 'mock')."\n";
    echo '- Overall Confidence: '.number_format($ocrResults['confidence'] * 100, 1)."%\n";
    echo '- Words Extracted: '.count($ocrResults['words'])."\n";
    echo '- Lines Processed: '.count($ocrResults['lines'])."\n\n";

    echo "📝 Raw OCR Text:\n";
    echo str_repeat('-', 60)."\n";
    echo $ocrResults['raw_text']."\n";
    echo str_repeat('-', 60)."\n\n";

    // Analyze for golf-specific data
    echo "⛳ Golf Data Analysis:\n";
    analyzeGolfData($ocrResults['raw_text'], 'Back Nine (Holes 10-18)');

    echo "\n".str_repeat('=', 80)."\n";
    echo "✅ MANUAL VERIFICATION CHECKLIST - BACK NINE:\n";
    echo str_repeat('=', 80)."\n";
    echo "Please verify the following data was extracted correctly:\n\n";
    echo "□ Course Name: Cyprus Point mentioned?\n";
    echo "□ Hole Numbers: Should see holes 10-18\n";
    echo "□ Par Values: Typically 3, 4, or 5 for each hole\n";
    echo "□ Yardages: Distance numbers for each hole (if visible)\n";
    echo "□ Player Names: Any player names on the scorecard\n";
    echo "□ Scores: Individual hole scores (if filled in)\n";
    echo "□ Back Nine Total: Sum of holes 10-18 (if calculated)\n";
    echo "□ Total Score: Overall 18-hole total (if calculated)\n";
    echo "□ Course Rating/Slope: Course difficulty ratings (if shown)\n\n";
    echo str_repeat('=', 80)."\n\n";

    // Assertions
    expect($ocrResults['raw_text'])->not->toBeEmpty('OCR should extract text from the image');
    expect($ocrResults['confidence'])->toBeGreaterThan(0, 'OCR should have some confidence level');
    expect($ocrResults['words'])->toBeArray('OCR should return word-level data');
    expect($ocrResults['lines'])->toBeArray('OCR should return line-level data');
});

it('preprocesses both scorecard images through enhancement pipeline', function () {
    echo "\n".str_repeat('=', 80)."\n";
    echo "📷 IMAGE PREPROCESSING PIPELINE TEST\n";
    echo str_repeat('=', 80)."\n";
    echo "Testing image enhancement and preprocessing on both scorecard images\n\n";

    $images = [
        'cyprus-point-front.jpg' => 'Front Nine',
        'cyprus-point-back.jpg' => 'Back Nine',
    ];

    foreach ($images as $filename => $description) {
        echo "🔧 Processing: {$filename} ({$description})\n";
        echo str_repeat('-', 50)."\n";

        $imagePath = base_path("tests/scorecards/{$filename}");
        expect($imagePath)->toBeFile("{$filename} must exist");

        // Copy to storage
        $imageContent = file_get_contents($imagePath);
        $storedPath = "scorecards/originals/{$filename}";
        Storage::disk('local')->put($storedPath, $imageContent);

        echo '- Original size: '.number_format(strlen($imageContent) / 1024, 2)." KB\n";
        echo "- Stored at: {$storedPath}\n";

        // Apply preprocessing
        echo "- Applying preprocessing (grayscale, contrast, sharpening)...\n";
        $processedPath = $this->imageService->preprocessImage($storedPath);
        echo "- Processed path: {$processedPath}\n";

        // Test corner detection
        echo "- Detecting scorecard corners for perspective correction...\n";
        $corners = $this->imageService->detectScorecardCorners($processedPath);

        echo "- Corner detection results:\n";
        foreach ($corners as $corner => $coords) {
            echo "  * {$corner}: ({$coords['x']}, {$coords['y']})\n";
        }

        echo "- ✅ Preprocessing completed\n\n";

        expect($corners)->toBeArray();
        expect($corners)->toHaveKey('top_left');
        expect($corners)->toHaveKey('top_right');
        expect($corners)->toHaveKey('bottom_left');
        expect($corners)->toHaveKey('bottom_right');
    }

    echo str_repeat('=', 80)."\n";
    echo "✅ All images successfully processed through enhancement pipeline\n";
    echo str_repeat('=', 80)."\n\n";
});

function analyzeGolfData(string $text, string $section): void
{
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $upperText = strtoupper($text);

    echo '- Total text lines: '.count($lines)."\n";
    echo '- Total characters: '.strlen($text)."\n\n";

    // Course name detection
    $courseKeywords = ['CYPRUS', 'POINT', 'GOLF', 'CLUB', 'COURSE'];
    $courseMatches = [];
    foreach ($lines as $line) {
        $upperLine = strtoupper($line);
        foreach ($courseKeywords as $keyword) {
            if (strpos($upperLine, $keyword) !== false && ! in_array($line, $courseMatches)) {
                $courseMatches[] = $line;
            }
        }
    }

    if (! empty($courseMatches)) {
        echo "🏌️ Course Name Candidates:\n";
        foreach ($courseMatches as $match) {
            echo "  • {$match}\n";
        }
        echo "\n";
    }

    // Hole number detection
    if (strpos($section, 'Front') !== false) {
        $expectedHoles = range(1, 9);
        echo "🕳️  Front Nine Hole Detection:\n";
    } else {
        $expectedHoles = range(10, 18);
        echo "🕳️  Back Nine Hole Detection:\n";
    }

    $foundHoles = [];
    foreach ($expectedHoles as $hole) {
        if (preg_match('/\b'.$hole.'\b/', $text)) {
            $foundHoles[] = $hole;
        }
    }

    echo '  • Expected holes: '.implode(', ', $expectedHoles)."\n";
    echo '  • Found holes: '.(empty($foundHoles) ? 'None detected' : implode(', ', $foundHoles))."\n";
    echo '  • Detection rate: '.round((count($foundHoles) / count($expectedHoles)) * 100, 1)."%\n\n";

    // Par value detection
    $parPattern = '/\b[345]\b/';
    preg_match_all($parPattern, $text, $parMatches);
    $parValues = array_count_values($parMatches[0]);

    if (! empty($parValues)) {
        echo "⭐ Par Value Analysis:\n";
        foreach ($parValues as $par => $count) {
            echo "  • Par {$par}: found {$count} times\n";
        }

        $totalPars = array_sum($parValues);
        $expectedPars = 9; // 9 holes per side
        echo "  • Total par values found: {$totalPars} (expected: {$expectedPars})\n\n";
    }

    // Score detection (looking for realistic golf scores)
    $scorePattern = '/\b([4-9]|[1-2][0-9])\b/';
    preg_match_all($scorePattern, $text, $scoreMatches);
    $potentialScores = array_unique($scoreMatches[1]);

    if (! empty($potentialScores)) {
        echo "📊 Potential Score Values:\n";
        sort($potentialScores, SORT_NUMERIC);
        echo '  • Detected: '.implode(', ', array_slice($potentialScores, 0, 15));
        if (count($potentialScores) > 15) {
            echo ' (and '.(count($potentialScores) - 15).' more)';
        }
        echo "\n\n";
    }

    // Date detection
    $datePatterns = [
        '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/',
        '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2}\b/',
        '/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* \d{1,2},? \d{4}\b/i',
    ];

    foreach ($datePatterns as $pattern) {
        if (preg_match($pattern, $text, $dateMatch)) {
            echo "📅 Date Found: {$dateMatch[0]}\n\n";
            break;
        }
    }

    // Player name detection (look for common name patterns)
    $namePatterns = [
        '/\b[A-Z][a-z]+ [A-Z][a-z]+\b/', // First Last
        '/\b[A-Z]\. [A-Z][a-z]+\b/', // F. Last
    ];

    $potentialNames = [];
    foreach ($namePatterns as $pattern) {
        if (preg_match_all($pattern, $text, $nameMatches)) {
            $potentialNames = array_merge($potentialNames, $nameMatches[0]);
        }
    }

    if (! empty($potentialNames)) {
        echo "👤 Potential Player Names:\n";
        foreach (array_unique($potentialNames) as $name) {
            echo "  • {$name}\n";
        }
        echo "\n";
    }

    // Course rating/slope detection
    if (preg_match('/(?:rating|slope)[:\s]*([0-9.]+)/i', $text, $ratingMatch)) {
        echo "🏌️ Course Rating/Slope: {$ratingMatch[1]}\n\n";
    }
}
