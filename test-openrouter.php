<?php

// Simple OpenRouter OCR test script
$openrouterKey = getenv('OPENROUTER_API_KEY');

if (! $openrouterKey) {
    echo "❌ OPENROUTER_API_KEY not set\n";
    echo "Please set your OpenRouter API key as an environment variable:\n";
    echo "export OPENROUTER_API_KEY='your-key-here'\n";
    exit(1);
}

function testOpenRouterConnection($apiKey)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$apiKey,
        'HTTP-Referer: http://localhost',
        'X-Title: Golf Scorecard OCR Scanner',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['response' => $response, 'status' => $httpCode];
}

function extractTextWithOpenRouter($imagePath, $apiKey)
{
    if (! file_exists($imagePath)) {
        throw new Exception("Image file not found: $imagePath");
    }

    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    $payload = [
        'model' => 'openai/gpt-4o-mini',
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
                            'url' => "data:$mimeType;base64,$imageData",
                            'detail' => 'high',
                        ],
                    ],
                ],
            ],
        ],
        'max_tokens' => 4000,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey,
        'HTTP-Referer: http://localhost',
        'X-Title: Golf Scorecard OCR Scanner',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenRouter API error: HTTP $httpCode - $response");
    }

    $data = json_decode($response, true);

    return $data['choices'][0]['message']['content'] ?? '';
}

echo "🔗 OPENROUTER CONNECTION TEST\n";
echo str_repeat('=', 80)."\n\n";

// Test connection first
echo "📡 Testing OpenRouter API connection...\n";
try {
    $connectionTest = testOpenRouterConnection($openrouterKey);

    if ($connectionTest['status'] === 200) {
        echo "✅ Connection successful!\n";
        $models = json_decode($connectionTest['response'], true);
        echo '📊 Available models: '.count($models['data'] ?? [])."\n\n";
    } else {
        echo '❌ Connection failed: HTTP '.$connectionTest['status']."\n";
        echo $connectionTest['response']."\n";
        exit(1);
    }
} catch (Exception $e) {
    echo '❌ Connection test failed: '.$e->getMessage()."\n";
    exit(1);
}

echo "🏌️ CYPRUS POINT OCR EXTRACTION\n";
echo str_repeat('=', 80)."\n\n";

// Process Front Nine
$frontPath = __DIR__.'/tests/scorecards/cyprus-point-front.jpg';
if (file_exists($frontPath)) {
    echo "📷 FRONT NINE PROCESSING:\n";
    echo "File: cyprus-point-front.jpg\n";

    try {
        $ocrText = extractTextWithOpenRouter($frontPath, $openrouterKey);

        echo "✅ OCR Success!\n";
        echo 'Text Length: '.strlen($ocrText)." characters\n\n";

        echo "📝 RAW OCR TEXT:\n";
        echo str_repeat('-', 60)."\n";
        echo $ocrText."\n";
        echo str_repeat('-', 60)."\n\n";

        // Parse the text for structured data
        $lines = explode("\n", $ocrText);
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
$backPath = __DIR__.'/tests/scorecards/cyprus-point-back.jpg';
if (file_exists($backPath)) {
    echo "📷 BACK NINE PROCESSING:\n";
    echo "File: cyprus-point-back.jpg\n";

    try {
        $ocrText = extractTextWithOpenRouter($backPath, $openrouterKey);

        echo "✅ OCR Success!\n";
        echo 'Text Length: '.strlen($ocrText)." characters\n\n";

        echo "📝 RAW OCR TEXT:\n";
        echo str_repeat('-', 60)."\n";
        echo $ocrText."\n";
        echo str_repeat('-', 60)."\n\n";

        // Parse the text for structured data
        $lines = explode("\n", $ocrText);
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
echo "\n📝 NEXT STEPS:\n";
echo "1. Review the extracted text above\n";
echo "2. Update your .env file to use OpenRouter:\n";
echo "   SCORECARD_OCR_PROVIDER=openrouter\n";
echo "   OPENROUTER_API_KEY=your-key-here\n";
echo "3. Run the ActualScorecardProcessingTest to see OCR results\n";
