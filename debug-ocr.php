<?php

require_once 'vendor/autoload.php';

use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;

// Create OCR service
$ocrService = new OcrService;
$imageService = new ImageProcessingService;

echo "🏌️ CYPRUS POINT OCR DATA EXTRACTION\n";
echo str_repeat('=', 80)."\n\n";

// Process Front Nine
$frontPath = base_path('tests/scorecards/cyprus-point-front.jpg');
if (file_exists($frontPath)) {
    echo "📷 FRONT NINE PROCESSING:\n";
    echo "File: $frontPath\n";

    try {
        $ocrResult = $ocrService->extractText($frontPath);

        echo "✅ OCR Success!\n";
        echo 'Confidence: '.$ocrResult['confidence']."%\n";
        echo 'Text Length: '.strlen($ocrResult['text'])." characters\n\n";

        echo "📝 RAW OCR TEXT:\n";
        echo str_repeat('-', 60)."\n";
        echo $ocrResult['text']."\n";
        echo str_repeat('-', 60)."\n\n";

        // Parse the text for structured data
        $lines = explode("\n", $ocrResult['text']);
        echo "📊 STRUCTURED DATA:\n";
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (! empty($line)) {
                echo sprintf("Line %02d: %s\n", $i + 1, $line);
            }
        }
        echo "\n";

    } catch (Exception $e) {
        echo '❌ OCR Failed: '.$e->getMessage()."\n\n";
    }
} else {
    echo "❌ Front nine image not found at: $frontPath\n\n";
}

// Process Back Nine
$backPath = base_path('tests/scorecards/cyprus-point-back.jpg');
if (file_exists($backPath)) {
    echo "📷 BACK NINE PROCESSING:\n";
    echo "File: $backPath\n";

    try {
        $ocrResult = $ocrService->extractText($backPath);

        echo "✅ OCR Success!\n";
        echo 'Confidence: '.$ocrResult['confidence']."%\n";
        echo 'Text Length: '.strlen($ocrResult['text'])." characters\n\n";

        echo "📝 RAW OCR TEXT:\n";
        echo str_repeat('-', 60)."\n";
        echo $ocrResult['text']."\n";
        echo str_repeat('-', 60)."\n\n";

        // Parse the text for structured data
        $lines = explode("\n", $ocrResult['text']);
        echo "📊 STRUCTURED DATA:\n";
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (! empty($line)) {
                echo sprintf("Line %02d: %s\n", $i + 1, $line);
            }
        }
        echo "\n";

    } catch (Exception $e) {
        echo '❌ OCR Failed: '.$e->getMessage()."\n\n";
    }
} else {
    echo "❌ Back nine image not found at: $backPath\n\n";
}

echo "🏁 EXTRACTION COMPLETE\n";
