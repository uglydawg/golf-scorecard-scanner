<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestOpenRouterApiCommand extends Command
{
    protected $signature = 'test:openrouter 
                          {image? : Path to test image (optional)}
                          {--simple : Test with simple text instead of image}
                          {--config : Show OpenRouter configuration}
                          {--models : List available models}';

    protected $description = 'Test OpenRouter API connection and functionality';

    public function handle(): int
    {
        $this->info('🔬 Testing OpenRouter API Connection');
        $this->newLine();

        // Show configuration if requested
        if ($this->option('config')) {
            $this->showConfiguration();

            return self::SUCCESS;
        }

        // List models if requested
        if ($this->option('models')) {
            $this->listModels();

            return self::SUCCESS;
        }

        // Get API configuration
        $apiKey = config('scorecard-scanner.ocr.providers.openrouter.api_key');
        $model = config('scorecard-scanner.ocr.providers.openrouter.model', 'openai/gpt-4o-mini');
        $baseUrl = config('scorecard-scanner.ocr.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $timeout = (int) config('scorecard-scanner.ocr.providers.openrouter.timeout', 60);

        if (empty($apiKey)) {
            $this->error('❌ OpenRouter API key not configured');
            $this->line('Set OPENROUTER_API_KEY in your .env file');

            return self::FAILURE;
        }

        $this->info('🔧 Configuration:');
        $this->line('   API Key: '.substr($apiKey, 0, 10).'...'.substr($apiKey, -5));
        $this->line("   Model: {$model}");
        $this->line("   Base URL: {$baseUrl}");
        $this->line("   Timeout: {$timeout}s");
        $this->newLine();

        // Test simple text completion first
        if ($this->option('simple')) {
            return $this->testSimpleCompletion($apiKey, $model, $baseUrl, $timeout);
        }

        // Test with image
        $imagePath = $this->argument('image');
        if ($imagePath) {
            return $this->testImageAnalysis($apiKey, $model, $baseUrl, $timeout, $imagePath);
        }

        // Default: test both
        $result1 = $this->testSimpleCompletion($apiKey, $model, $baseUrl, $timeout);
        if ($result1 === self::SUCCESS) {
            $this->newLine();
            // Use a default test image if available
            $defaultImage = 'tests/scorecards/cyprus-point-front.jpg';
            if (file_exists($defaultImage)) {
                return $this->testImageAnalysis($apiKey, $model, $baseUrl, $timeout, $defaultImage);
            }
        }

        return $result1;
    }

    private function showConfiguration(): void
    {
        $config = config('scorecard-scanner.ocr.providers.openrouter', []);

        $this->info('📋 OpenRouter Configuration:');
        $this->table(['Setting', 'Value'], [
            ['Driver', $config['driver'] ?? 'N/A'],
            ['API Key', ! empty($config['api_key']) ? substr($config['api_key'], 0, 10).'...'.substr($config['api_key'], -5) : 'Not Set'],
            ['Model', $config['model'] ?? 'N/A'],
            ['Max Tokens', $config['max_tokens'] ?? 'N/A'],
            ['Timeout', $config['timeout'] ?? 'N/A'],
            ['Base URL', $config['base_url'] ?? 'N/A'],
        ]);

        $this->newLine();
        $this->info('🔍 Environment Variables:');
        $envVars = [
            'OPENROUTER_API_KEY' => env('OPENROUTER_API_KEY') ? 'Set' : 'Not Set',
            'OPENROUTER_OCR_MODEL' => env('OPENROUTER_OCR_MODEL', 'Default'),
            'OPENROUTER_MAX_TOKENS' => env('OPENROUTER_MAX_TOKENS', 'Default'),
            'OPENROUTER_TIMEOUT' => env('OPENROUTER_TIMEOUT', 'Default'),
            'OPENROUTER_BASE_URL' => env('OPENROUTER_BASE_URL', 'Default'),
        ];

        foreach ($envVars as $key => $value) {
            $this->line("   {$key}: {$value}");
        }
    }

    private function listModels(): int
    {
        $apiKey = config('scorecard-scanner.ocr.providers.openrouter.api_key');
        $baseUrl = config('scorecard-scanner.ocr.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');

        if (empty($apiKey)) {
            $this->error('❌ OpenRouter API key not configured');

            return self::FAILURE;
        }

        $this->info('📋 Fetching available models...');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->get($baseUrl.'/models');

            if ($response->successful()) {
                $models = $response->json()['data'] ?? [];

                $this->info('✅ Found '.count($models).' models:');
                $this->newLine();

                $modelTable = [];
                foreach (array_slice($models, 0, 20) as $model) { // Show first 20 models
                    $modelTable[] = [
                        $model['id'] ?? 'N/A',
                        $model['name'] ?? 'N/A',
                        isset($model['context_length']) ? number_format($model['context_length']) : 'N/A',
                        isset($model['pricing']['prompt']) ? '$'.$model['pricing']['prompt'] : 'N/A',
                    ];
                }

                $this->table(['Model ID', 'Name', 'Context Length', 'Prompt Price'], $modelTable);

                if (count($models) > 20) {
                    $this->line('... and '.(count($models) - 20).' more models');
                }

                return self::SUCCESS;
            } else {
                $this->error('❌ Failed to fetch models: '.$response->body());

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error fetching models: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function testSimpleCompletion(string $apiKey, string $model, string $baseUrl, int $timeout): int
    {
        $this->info('🚀 Testing Simple Text Completion...');

        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'Golf Scorecard OCR Scanner Test',
                ])
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Hello! Please respond with "OpenRouter API is working correctly" if you can see this message.',
                        ],
                    ],
                    'max_tokens' => 50,
                ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';

                $this->info('✅ Simple completion successful!');
                $this->line('   Response: '.trim($content));
                $this->line("   Processing time: {$processingTime}ms");
                $this->line('   Model used: '.($result['model'] ?? $model));
                $this->line('   Tokens used: '.($result['usage']['total_tokens'] ?? 'N/A'));

                return self::SUCCESS;
            } else {
                $this->error('❌ Simple completion failed:');
                $this->line('   Status: '.$response->status());
                $this->line('   Response: '.$response->body());

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Simple completion error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function testImageAnalysis(string $apiKey, string $model, string $baseUrl, int $timeout, string $imagePath): int
    {
        $this->info("🖼️  Testing Image Analysis with: {$imagePath}");

        if (! file_exists($imagePath)) {
            $this->error("❌ Image file not found: {$imagePath}");

            return self::FAILURE;
        }

        $mimeType = mime_content_type($imagePath);
        if (! str_starts_with($mimeType, 'image/')) {
            $this->error("❌ File is not an image: {$imagePath}");

            return self::FAILURE;
        }

        $this->line("   File: {$imagePath}");
        $this->line("   Type: {$mimeType}");
        $this->line('   Size: '.number_format(filesize($imagePath)).' bytes');

        $startTime = microtime(true);

        try {
            // Encode image as base64
            $imageData = base64_encode(file_get_contents($imagePath));

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'Golf Scorecard OCR Scanner Test',
                ])
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Please analyze this golf scorecard image and extract the course name, tee information, and any hole data you can see. Be concise.',
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
                    'max_tokens' => 500,
                ]);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';

                $this->info('✅ Image analysis successful!');
                $this->line("   Processing time: {$processingTime}ms");
                $this->line('   Model used: '.($result['model'] ?? $model));
                $this->line('   Tokens used: '.($result['usage']['total_tokens'] ?? 'N/A'));
                $this->newLine();
                $this->info('📝 Extracted Content:');
                $this->line($content);

                return self::SUCCESS;
            } else {
                $this->error('❌ Image analysis failed:');
                $this->line('   Status: '.$response->status());
                $this->line('   Response: '.$response->body());

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Image analysis error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
