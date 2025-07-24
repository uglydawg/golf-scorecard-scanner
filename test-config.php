<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->boot();

echo "🔧 CONFIGURATION TEST\n";
echo str_repeat('=', 50)."\n\n";

echo 'Current OCR Provider: '.config('scorecard-scanner.ocr.default', 'not set')."\n";
echo 'OpenRouter API Key: '.(config('scorecard-scanner.ocr.providers.openrouter.api_key') ? 'SET' : 'NOT SET')."\n";
echo 'OpenRouter Model: '.config('scorecard-scanner.ocr.providers.openrouter.model', 'not set')."\n";

// Test environment variables
echo "\nEnvironment Variables:\n";
echo 'SCORECARD_OCR_PROVIDER: '.env('SCORECARD_OCR_PROVIDER', 'not set')."\n";
echo 'OPENROUTER_API_KEY: '.(env('OPENROUTER_API_KEY') ? 'SET' : 'NOT SET')."\n";

echo "\n✅ Configuration check complete\n";
