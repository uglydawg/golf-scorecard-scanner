<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ScorecardScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScorecardScan>
 */
class AppScorecardScanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScorecardScan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_image_path' => 'scorecards/test-scorecard.jpg',
            'processed_image_path' => null,
            'raw_ocr_data' => [
                'text' => 'Test Golf Course\nPar: 72\nDate: 2024-07-24',
                'confidence' => 95.5,
            ],
            'parsed_data' => [
                'course_name' => 'Test Golf Course',
                'par' => 72,
                'date' => '2024-07-24',
                'scores' => [],
            ],
            'confidence_scores' => [
                'overall' => 95.5,
                'course_name' => 98.2,
                'par' => 92.1,
            ],
            'status' => 'completed',
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the scan is still processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'parsed_data' => null,
            'confidence_scores' => null,
        ]);
    }

    /**
     * Indicate that the scan failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => 'OCR processing failed',
            'parsed_data' => null,
            'confidence_scores' => null,
        ]);
    }
}
