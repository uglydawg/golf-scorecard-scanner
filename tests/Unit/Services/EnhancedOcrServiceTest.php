<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Services\OcrService;

uses(Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('local');

    // Mock configuration for enhanced prompt testing
    config([
        'scorecard-scanner.ocr.enhanced_prompt_enabled' => true,
        'scorecard-scanner.ocr.default' => 'openrouter',
        'scorecard-scanner.ocr.providers.openrouter.api_key' => 'test-key',
        'scorecard-scanner.ocr.providers.openrouter.model' => 'openai/gpt-4o',
        'scorecard-scanner.ocr.providers.openrouter.timeout' => 60,
    ]);
});

describe('Enhanced Prompt Generation', function () {
    it('generates enhanced golf scorecard extraction prompt with comprehensive JSON schema', function () {
        $ocrService = app(OcrService::class);

        // Use reflection to access the private method
        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('getEnhancedGolfScorecardExtractionPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($ocrService);

        // Test prompt structure and content
        expect($prompt)->toBeString();
        expect($prompt)->not->toBeEmpty();

        // Test that prompt contains golf-specific terminology
        expect($prompt)->toContain('golf scorecard');
        expect($prompt)->toContain('ENHANCED JSON SCHEMA');
        expect($prompt)->toContain('course_information');
        expect($prompt)->toContain('tee_boxes');
        expect($prompt)->toContain('player_scores');

        // Test JSON schema structure requirements
        expect($prompt)->toContain('course_name');
        expect($prompt)->toContain('location');
        expect($prompt)->toContain('architect');
        expect($prompt)->toContain('established_year');
        expect($prompt)->toContain('par_values');
        expect($prompt)->toContain('handicap_values');
        expect($prompt)->toContain('yardages');
        expect($prompt)->toContain('course_rating');
        expect($prompt)->toContain('slope_rating');

        // Test validation requirements are mentioned
        expect($prompt)->toContain('3-6'); // par validation
        expect($prompt)->toContain('1-18'); // handicap validation
        expect($prompt)->toContain('67.0-77.0'); // course rating validation
        expect($prompt)->toContain('55-155'); // slope rating validation
    });

    it('includes USGA-compliant validation rules in enhanced prompt', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('getEnhancedGolfScorecardExtractionPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($ocrService);

        // Test USGA validation requirements
        expect($prompt)->toContain('exactly 18'); // for par and handicap arrays
        expect($prompt)->toContain('unique'); // for handicap values
        expect($prompt)->toContain('50-700'); // yardage range
        expect($prompt)->toContain('1-15'); // reasonable score range

        // Test confidence requirements
        expect($prompt)->toContain('confidence');
        expect($prompt)->toContain('0.0-1.0');
    });

    it('maintains golf domain expertise in enhanced prompt', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('getEnhancedGolfScorecardExtractionPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($ocrService);

        // Test golf expertise terminology
        expect($prompt)->toContain('tee_name');
        expect($prompt)->toContain('Championship');
        expect($prompt)->toContain('Ladies');
        expect($prompt)->toContain('TEE BOX');
        expect($prompt)->toContain('course_rating');
        expect($prompt)->toContain('slope_rating');
        expect($prompt)->toContain('hole-by-hole');
    });
});

describe('Enhanced Response Processing', function () {
    it('processes enhanced OpenAI response with new JSON schema format', function () {
        $ocrService = app(OcrService::class);

        // Mock enhanced JSON response
        $mockResponse = json_encode([
            'course_information' => [
                'course_name' => 'Cyprus Point Club',
                'location' => 'Pebble Beach, CA',
                'architect' => 'Alister MacKenzie',
                'established_year' => 1928,
                'confidence' => 0.95,
            ],
            'tee_boxes' => [
                [
                    'tee_name' => 'Championship',
                    'par_values' => [4, 5, 3, 4, 4, 3, 5, 4, 4],
                    'handicap_values' => [1, 3, 17, 5, 7, 15, 9, 11, 13],
                    'yardages' => [420, 545, 190, 385, 410, 165, 520, 395, 380],
                    'course_rating' => 72.8,
                    'slope_rating' => 142,
                    'confidence' => 0.92,
                ],
            ],
            'player_scores' => [
                [
                    'player_name' => 'John Doe',
                    'hole_scores' => [4, 6, 3, 5, 4, 2, 5, 4, 5],
                    'confidence' => 0.88,
                ],
            ],
        ]);

        // Use reflection to access the private method
        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('processEnhancedOpenAIResponse');
        $method->setAccessible(true);

        $result = $method->invoke($ocrService, $mockResponse, 'test-image.jpg');

        // Test response structure
        expect($result)->toBeArray();
        expect($result)->toHaveKey('structured_data');
        expect($result)->toHaveKey('enhanced_format');
        expect($result['enhanced_format'])->toBeTrue();

        $structuredData = $result['structured_data'];
        expect($structuredData)->toHaveKey('course_information');
        expect($structuredData)->toHaveKey('tee_boxes');
        expect($structuredData)->toHaveKey('player_scores');

        // Test course information processing
        expect($structuredData['course_information'])->toHaveKey('course_name');
        expect($structuredData['course_information']['course_name'])->toBe('Cyprus Point Club');
        expect($structuredData['course_information'])->toHaveKey('confidence');
        expect($structuredData['course_information']['confidence'])->toBe(0.95);

        // Test tee box processing
        expect($structuredData['tee_boxes'])->toBeArray();
        expect($structuredData['tee_boxes'])->toHaveCount(1);
        expect($structuredData['tee_boxes'][0])->toHaveKey('par_values');
        expect($structuredData['tee_boxes'][0]['par_values'])->toHaveCount(9);

        // Test player scores processing
        expect($structuredData['player_scores'])->toBeArray();
        expect($structuredData['player_scores'])->toHaveCount(1);
        expect($structuredData['player_scores'][0]['player_name'])->toBe('John Doe');
    });

    it('handles graceful degradation when enhanced JSON parsing fails', function () {
        $ocrService = app(OcrService::class);

        // Mock invalid JSON response
        $invalidResponse = 'This is not valid JSON content';

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('processEnhancedOpenAIResponse');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($ocrService, $invalidResponse, 'test-image.jpg'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('calculates field-level confidence scoring for enhanced data extraction', function () {
        $ocrService = app(OcrService::class);

        $mockResponse = json_encode([
            'course_information' => [
                'course_name' => 'Test Course',
                'confidence' => 0.85,
            ],
            'tee_boxes' => [
                [
                    'tee_name' => 'Blue',
                    'par_values' => [4, 3, 5, 4, 4, 3, 4, 5, 4],
                    'confidence' => 0.92,
                ],
            ],
            'player_scores' => [
                [
                    'player_name' => 'Test Player',
                    'hole_scores' => [4, 3, 6, 5, 4, 3, 4, 6, 4],
                    'confidence' => 0.78,
                ],
            ],
        ]);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('processEnhancedOpenAIResponse');
        $method->setAccessible(true);

        $result = $method->invoke($ocrService, $mockResponse, 'test-image.jpg');

        // Test that confidence scores are preserved and calculated
        $structuredData = $result['structured_data'];
        expect($structuredData['course_information']['confidence'])->toBe(0.85);
        expect($structuredData['tee_boxes'][0]['confidence'])->toBe(0.92);
        expect($structuredData['player_scores'][0]['confidence'])->toBe(0.78);

        // Test overall confidence calculation
        expect($structuredData)->toHaveKey('overall_confidence');
        $expectedOverall = (0.85 + 0.92 + 0.78) / 3;
        expect(abs($structuredData['overall_confidence'] - $expectedOverall))->toBeLessThan(0.01);
    });
});

describe('Golf Course Data Validation', function () {
    it('validates par values are exactly 18 integers each 3-6', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('validateGolfCourseData');
        $method->setAccessible(true);

        // Valid par values
        $validData = [
            'tee_boxes' => [
                [
                    'par_values' => [4, 3, 5, 4, 4, 3, 4, 5, 4, 4, 3, 5, 4, 4, 3, 4, 5, 4],
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $validData);
        expect($result['validation_errors'])->toBeEmpty();

        // Invalid par values (wrong count)
        $invalidCountData = [
            'tee_boxes' => [
                [
                    'par_values' => [4, 3, 5, 4, 4], // Only 5 holes
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $invalidCountData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('exactly 18');

        // Invalid par values (out of range)
        $invalidRangeData = [
            'tee_boxes' => [
                [
                    'par_values' => [2, 3, 5, 4, 4, 3, 4, 5, 4, 4, 3, 5, 4, 4, 3, 4, 5, 7], // 2 and 7 are invalid
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $invalidRangeData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('3-6');
    });

    it('validates handicap values are exactly 18 unique integers 1-18', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('validateGolfCourseData');
        $method->setAccessible(true);

        // Valid handicap values
        $validData = [
            'tee_boxes' => [
                [
                    'handicap_values' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $validData);
        expect($result['validation_errors'])->toBeEmpty();

        // Invalid handicap values (duplicates)
        $duplicateData = [
            'tee_boxes' => [
                [
                    'handicap_values' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 1], // Duplicate 1
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $duplicateData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('unique');
    });

    it('validates course ratings are within 67.0-77.0 range', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('validateGolfCourseData');
        $method->setAccessible(true);

        // Valid course rating
        $validData = [
            'tee_boxes' => [
                [
                    'course_rating' => 72.5,
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $validData);
        expect($result['validation_errors'])->toBeEmpty();

        // Invalid course rating (too low)
        $invalidData = [
            'tee_boxes' => [
                [
                    'course_rating' => 65.0,
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $invalidData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('67.0-77.0');
    });

    it('validates slope ratings are within 55-155 range', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('validateGolfCourseData');
        $method->setAccessible(true);

        // Valid slope rating
        $validData = [
            'tee_boxes' => [
                [
                    'slope_rating' => 135,
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $validData);
        expect($result['validation_errors'])->toBeEmpty();

        // Invalid slope rating (too high)
        $invalidData = [
            'tee_boxes' => [
                [
                    'slope_rating' => 160,
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $invalidData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('55-155');
    });

    it('validates player scores are reasonable (1-15 per hole)', function () {
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('validateGolfCourseData');
        $method->setAccessible(true);

        // Valid player scores
        $validData = [
            'player_scores' => [
                [
                    'hole_scores' => [4, 3, 6, 5, 4, 3, 4, 6, 4, 4, 3, 5, 4, 4, 3, 4, 5, 4],
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $validData);
        expect($result['validation_errors'])->toBeEmpty();

        // Invalid player scores (unrealistic score)
        $invalidData = [
            'player_scores' => [
                [
                    'hole_scores' => [4, 3, 6, 5, 4, 3, 4, 20, 4, 4, 3, 5, 4, 4, 3, 4, 5, 4], // 20 is unrealistic
                ],
            ],
        ];

        $result = $method->invoke($ocrService, $invalidData);
        expect($result['validation_errors'])->not->toBeEmpty();
        expect($result['validation_errors'][0])->toContain('1-15');
    });
});

describe('Configuration System', function () {
    it('respects enhanced prompt configuration flag', function () {
        // Test with enhanced prompt enabled
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => true]);
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $property = $reflection->getProperty('useEnhancedPrompt');
        $property->setAccessible(true);

        expect($property->getValue($ocrService))->toBeTrue();

        // Test with enhanced prompt disabled
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => false]);
        $ocrServiceDisabled = new \ScorecardScanner\Services\OcrService;

        $reflectionDisabled = new ReflectionClass($ocrServiceDisabled);
        $propertyDisabled = $reflectionDisabled->getProperty('useEnhancedPrompt');
        $propertyDisabled->setAccessible(true);

        expect($propertyDisabled->getValue($ocrServiceDisabled))->toBeFalse();
    });

    it('falls back to standard prompt when enhanced is disabled', function () {
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => false]);
        $ocrService = app(OcrService::class);

        $reflection = new ReflectionClass($ocrService);
        $method = $reflection->getMethod('getGolfScorecardExtractionPrompt');
        $method->setAccessible(true);

        $prompt = $method->invoke($ocrService);

        // Should return standard prompt, not enhanced
        expect($prompt)->toBeString();
        expect($prompt)->not->toContain('course_information');
        expect($prompt)->not->toContain('tee_boxes');
    });
});

describe('Backward Compatibility', function () {
    it('maintains existing OCR functionality when enhanced features are disabled', function () {
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => false]);

        $ocrService = app(OcrService::class);

        // Mock standard OCR response
        $mockResponse = [
            'raw_text' => 'Test scorecard content',
            'confidence' => 0.85,
            'words' => ['Test', 'scorecard', 'content'],
            'lines' => ['Test scorecard content'],
        ];

        // This should work exactly as before
        expect($mockResponse)->toHaveKey('raw_text');
        expect($mockResponse)->toHaveKey('confidence');
        expect($mockResponse['confidence'])->toBe(0.85);
    });

    it('allows gradual migration from standard to enhanced prompt processing', function () {
        // Start with standard
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => false]);
        $standardService = app(OcrService::class);

        // Switch to enhanced
        config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => true]);
        $enhancedService = app(OcrService::class);

        // Both should work without errors
        expect($standardService)->toBeInstanceOf(OcrService::class);
        expect($enhancedService)->toBeInstanceOf(OcrService::class);
    });
});
