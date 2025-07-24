<?php

// Simple OCR extraction without Laravel dependencies
$openaiKey = getenv('OPENAI_API_KEY');

if (! $openaiKey) {
    echo "❌ OPENAI_API_KEY not set\n";
    exit(1);
}

function extractTextViaOpenAI($imagePath, $apiKey)
{
    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Extract all text from this golf scorecard image. Return only the raw text, preserving the layout and structure as much as possible.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$mimeType;base64,$imageData",
                        ],
                    ],
                ],
            ],
        ],
        'max_tokens' => 1000,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenAI API error: HTTP $httpCode - $response");
    }

    $data = json_decode($response, true);

    return $data['choices'][0]['message']['content'] ?? '';
}

echo "🏌️ CYPRUS POINT OCR DATA EXTRACTION\n";
echo str_repeat('=', 80)."\n\n";

// Process Front Nine
$frontPath = __DIR__.'/tests/scorecards/cyprus-point-front.jpg';
if (file_exists($frontPath)) {
    echo "📷 FRONT NINE PROCESSING:\n";
    echo "File: cyprus-point-front.jpg\n";

    try {
        $ocrText = extractTextViaOpenAI($frontPath, $openaiKey);

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
        $ocrText = extractTextViaOpenAI($backPath, $openaiKey);

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
