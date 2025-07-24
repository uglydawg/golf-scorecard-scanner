<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('can connect to openai api', function () {
    $apiKey = env('OPENAI_API_KEY');

    echo "\n".str_repeat('=', 60)."\n";
    echo "🤖 OPENAI API CONNECTION TEST\n";
    echo str_repeat('=', 60)."\n";

    // Check if API key is configured
    if (empty($apiKey) || $apiKey === 'your_openai_api_key_here') {
        echo "❌ FAILED: OpenAI API key not configured\n";
        echo "Please set OPENAI_API_KEY in your .env file\n";
        echo str_repeat('=', 60)."\n\n";
        fail('OpenAI API key not configured');
    }

    echo '✅ API Key: '.substr($apiKey, 0, 7).'...'.substr($apiKey, -4)."\n";
    echo "🔍 Testing API connection...\n\n";

    try {
        // Test with a simple text-only request first
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello! Please respond with just "OpenAI API is working!" to confirm the connection.',
                    ],
                ],
                'max_tokens' => 50,
            ]);

        if ($response->successful()) {
            $result = $response->json();
            $message = $result['choices'][0]['message']['content'] ?? 'No response';

            echo "🎉 SUCCESS: API Connection Working!\n";
            echo '📝 Response: '.trim($message)."\n";
            echo '⚡ Model: '.($result['model'] ?? 'unknown')."\n";
            echo '🔢 Tokens used: '.($result['usage']['total_tokens'] ?? 'unknown')."\n";
            echo "💰 Estimated cost: ~$0.0001\n\n";

            echo "✅ READY FOR SCORECARD OCR TESTING!\n";
            echo "Run this command to test with your Cyprus Point images:\n";
            echo "vendor/bin/phpunit --filter test_cyprus_point_front_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php\n";

        } else {
            echo "❌ FAILED: API request failed\n";
            echo 'Status: '.$response->status()."\n";
            echo 'Error: '.$response->body()."\n";
            fail('OpenAI API request failed: '.$response->body());
        }

    } catch (\Exception $e) {
        echo "❌ FAILED: Exception occurred\n";
        echo 'Error: '.$e->getMessage()."\n";
        fail('OpenAI API test failed: '.$e->getMessage());
    }

    echo str_repeat('=', 60)."\n\n";

    // Basic assertions
    expect($apiKey)->not->toBeEmpty()
        ->and($apiKey)->toStartWith('sk-');
});

it('has correct scorecard scanner configuration', function () {
    echo "\n".str_repeat('=', 60)."\n";
    echo "⚙️  SCORECARD SCANNER CONFIGURATION TEST\n";
    echo str_repeat('=', 60)."\n";

    $provider = config('scorecard-scanner.ocr.default');
    $openaiConfig = config('scorecard-scanner.ocr.providers.openai');

    echo '🔧 Current OCR Provider: '.($provider ?? 'not set')."\n";
    echo '🔑 OpenAI API Key: '.(isset($openaiConfig['api_key']) ? 'configured' : 'not configured')."\n";
    echo '🤖 OpenAI Model: '.($openaiConfig['model'] ?? 'not set')."\n";
    echo '⏱️  Timeout: '.($openaiConfig['timeout'] ?? 'not set')." seconds\n";
    echo '🎯 Max Tokens: '.($openaiConfig['max_tokens'] ?? 'not set')."\n\n";

    if ($provider === 'openai') {
        echo "✅ Configuration looks good for OpenAI OCR!\n";
    } else {
        echo "⚠️  Provider is set to '{$provider}', change to 'openai' for OCR testing\n";
        echo "Add this to your .env: SCORECARD_OCR_PROVIDER=openai\n";
    }

    echo str_repeat('=', 60)."\n\n";

    expect($provider)->not->toBeNull()
        ->and($openaiConfig)->toBeArray();
});
