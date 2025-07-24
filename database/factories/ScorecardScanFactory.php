<?php

declare(strict_types=1);

namespace Database\Factories;

use ScorecardScanner\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\ScorecardScanner\Models\ScorecardScan>
 */
class ScorecardScanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \ScorecardScanner\Models\ScorecardScan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_image_path' => 'scorecard-scans/originals/' . $this->faker->uuid() . '.jpg',
            'processed_image_path' => 'scorecard-scans/processed/' . $this->faker->uuid() . '.jpg',
            'status' => $this->faker->randomElement(['processing', 'completed', 'failed']),
            'raw_ocr_data' => [
                'raw_text' => $this->faker->paragraph(),
                'confidence' => $this->faker->randomFloat(2, 0.7, 1.0),
            ],
            'parsed_data' => [
                'course_name' => $this->faker->company() . ' Golf Course',
                'date' => $this->faker->date(),
                'tee_name' => $this->faker->randomElement(['Championship', 'Blue', 'White', 'Red']),
                'players' => [$this->faker->name(), $this->faker->name()],
            ],
            'confidence_scores' => [
                'course_name' => $this->faker->randomFloat(2, 0.8, 1.0),
                'date' => $this->faker->randomFloat(2, 0.8, 1.0),
                'tee_name' => $this->faker->randomFloat(2, 0.8, 1.0),
            ],
            'error_message' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'parsed_data' => null,
            'confidence_scores' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
            'parsed_data' => null,
            'confidence_scores' => null,
        ]);
    }
}
