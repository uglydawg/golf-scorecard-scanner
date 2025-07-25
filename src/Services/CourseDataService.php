<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ScorecardScanner\Models\Course;
use ScorecardScanner\Models\Round;
use ScorecardScanner\Models\RoundScore;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Models\UnverifiedCourse;

class CourseDataService
{
    public function __construct(
        private float $confidenceThreshold = 0.85,
        private int $minimumDataCompleteness = 70
    ) {}

    /**
     * Convert scorecard scan data into golf course and round records
     */
    public function processScanToCourseData(ScorecardScan $scan): array
    {
        $results = [
            'course_created' => false,
            'course_id' => null,
            'round_created' => false,
            'round_id' => null,
            'scores_created' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Parse the scan data
            $parsedData = $this->extractParsedData($scan);
            $golfCourseData = $this->extractGolfCourseData($scan);

            if (empty($parsedData) && empty($golfCourseData)) {
                $results['errors'][] = 'No valid golf course data found in scan';

                return $results;
            }

            // Determine which data source to use (prefer enhanced data if available)
            $courseData = $this->selectBestCourseData($parsedData, $golfCourseData);

            // Validate data quality
            $validation = $this->validateCourseData($courseData);
            if (! $validation['valid']) {
                $results['errors'] = array_merge($results['errors'], $validation['errors']);
                if (! $validation['can_proceed']) {
                    return $results;
                }
            }
            $results['warnings'] = array_merge($results['warnings'], $validation['warnings']);

            // Process course creation/matching
            $courseResult = $this->findOrCreateCourse($courseData);
            $results['course_created'] = $courseResult['created'];
            $results['course_id'] = $courseResult['course_id'];

            if ($courseResult['added_to_unverified']) {
                $results['warnings'][] = 'Course added to unverified database for admin review';
            }

            // Create round data if we have scoring information
            if (isset($courseData['players']) && ! empty($courseData['players']) && $results['course_id']) {
                $roundResult = $this->createRoundFromScan($scan, $results['course_id'], $courseData);
                $results['round_created'] = $roundResult['created'];
                $results['round_id'] = $roundResult['round_id'];
                $results['scores_created'] = $roundResult['scores_created'];

                if (! empty($roundResult['errors'])) {
                    $results['errors'] = array_merge($results['errors'], $roundResult['errors']);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to process scan to course data', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $results['errors'][] = 'Processing failed: '.$e->getMessage();
        }

        return $results;
    }

    /**
     * Extract parsed data from scan (enhanced OCR data)
     */
    private function extractParsedData(ScorecardScan $scan): array
    {
        $parsedData = $scan->parsed_data ?? [];

        // Check for enhanced data structure
        if (isset($parsedData['enhanced_data'])) {
            return $parsedData['enhanced_data'];
        }

        return $parsedData;
    }

    /**
     * Extract golf course properties from raw OCR data
     */
    private function extractGolfCourseData(ScorecardScan $scan): array
    {
        $rawData = $scan->raw_ocr_data ?? [];

        if (isset($rawData['golf_course_properties'])) {
            return $rawData['golf_course_properties'];
        }

        if (isset($rawData['structured_data'])) {
            return $rawData['structured_data'];
        }

        return [];
    }

    /**
     * Select the best data source between parsed and golf course data
     */
    private function selectBestCourseData(array $parsedData, array $golfCourseData): array
    {
        // Prefer enhanced/parsed data if it has more completeness
        $parsedCompleteness = $this->calculateDataCompleteness($parsedData);
        $golfCompleteness = $this->calculateDataCompleteness($golfCourseData);

        if ($parsedCompleteness >= $golfCompleteness) {
            return array_merge($golfCourseData, $parsedData); // Merge with parsed taking priority
        }

        return array_merge($parsedData, $golfCourseData); // Merge with golf taking priority
    }

    /**
     * Calculate data completeness percentage
     */
    private function calculateDataCompleteness(array $data): float
    {
        if (isset($data['data_completeness'])) {
            return (float) $data['data_completeness'] * 100;
        }

        $requiredFields = ['course_name', 'tee_name', 'course_rating', 'slope_rating'];
        $optionalFields = ['par_values', 'handicap_values', 'total_par', 'total_yardage', 'location'];

        $requiredCount = 0;
        $optionalCount = 0;

        foreach ($requiredFields as $field) {
            if (! empty($data[$field])) {
                $requiredCount++;
            }
        }

        foreach ($optionalFields as $field) {
            if (! empty($data[$field])) {
                $optionalCount++;
            }
        }

        $requiredWeight = 0.7;
        $optionalWeight = 0.3;

        $requiredScore = ($requiredCount / count($requiredFields)) * $requiredWeight;
        $optionalScore = ($optionalCount / count($optionalFields)) * $optionalWeight;

        return ($requiredScore + $optionalScore) * 100;
    }

    /**
     * Validate course data quality and completeness
     */
    private function validateCourseData(array $data): array
    {
        $validation = [
            'valid' => true,
            'can_proceed' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check required fields
        if (empty($data['course_name'])) {
            $validation['errors'][] = 'Course name is required';
            $validation['can_proceed'] = false;
        }

        // Validate golf-specific data
        if (isset($data['course_rating']) && ($data['course_rating'] < 67.0 || $data['course_rating'] > 77.0)) {
            $validation['warnings'][] = 'Course rating outside typical range (67.0-77.0)';
        }

        if (isset($data['slope_rating']) && ($data['slope_rating'] < 55 || $data['slope_rating'] > 155)) {
            $validation['warnings'][] = 'Slope rating outside USGA range (55-155)';
        }

        if (isset($data['par_values']) && is_array($data['par_values'])) {
            $parValues = $data['par_values'];
            if (count($parValues) !== 18) {
                $validation['warnings'][] = 'Par values count is not 18 holes';
            }

            foreach ($parValues as $par) {
                if ($par < 3 || $par > 6) {
                    $validation['warnings'][] = 'Par values contain invalid values (not 3-6)';
                    break;
                }
            }
        }

        // Check data completeness
        $completeness = $this->calculateDataCompleteness($data);
        if ($completeness < $this->minimumDataCompleteness) {
            $validation['warnings'][] = "Data completeness below threshold ({$completeness}% < {$this->minimumDataCompleteness}%)";
        }

        $validation['valid'] = empty($validation['errors']);

        return $validation;
    }

    /**
     * Find existing course or create new one
     */
    private function findOrCreateCourse(array $data): array
    {
        $result = [
            'created' => false,
            'course_id' => null,
            'added_to_unverified' => false,
        ];

        $courseName = $data['course_name'] ?? '';
        $teeName = $data['tee_name'] ?? '';

        // Try to find existing verified course
        $existingCourse = Course::where('name', 'ILIKE', "%{$courseName}%")
            ->where('tee_name', 'ILIKE', "%{$teeName}%")
            ->where('is_verified', true)
            ->first();

        if ($existingCourse) {
            $result['course_id'] = $existingCourse->id;

            return $result;
        }

        // Check confidence before auto-creating
        $confidence = $data['confidence_score'] ?? 0.0;
        if ($confidence < $this->confidenceThreshold) {
            // Add to unverified courses for manual review
            $this->addToUnverifiedCourses($data);
            $result['added_to_unverified'] = true;

            return $result;
        }

        // Create verified course if confidence is high enough
        try {
            $courseData = [
                'name' => $courseName,
                'tee_name' => $teeName,
                'par_values' => $this->extractParValues($data),
                'handicap_values' => $this->extractHandicapValues($data),
                'slope' => $this->extractSlope($data),
                'rating' => $this->extractRating($data),
                'location' => $this->extractLocation($data),
                'is_verified' => true,
            ];

            $course = Course::create($courseData);
            $result['created'] = true;
            $result['course_id'] = $course->id;

            Log::info('Auto-created verified course from high-confidence scan', [
                'course_id' => $course->id,
                'course_name' => $courseName,
                'confidence' => $confidence,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create course', [
                'course_name' => $courseName,
                'error' => $e->getMessage(),
            ]);

            // Fallback to unverified
            $this->addToUnverifiedCourses($data);
            $result['added_to_unverified'] = true;
        }

        return $result;
    }

    /**
     * Add course data to unverified courses table
     */
    private function addToUnverifiedCourses(array $data): void
    {
        $courseData = [
            'name' => $data['course_name'] ?? '',
            'tee_name' => $data['tee_name'] ?? '',
            'par_values' => $this->extractParValues($data),
            'handicap_values' => $this->extractHandicapValues($data),
            'slope' => $this->extractSlope($data),
            'rating' => $this->extractRating($data),
            'location' => $this->extractLocation($data),
        ];

        // Check if similar unverified course already exists
        $existing = UnverifiedCourse::where('name', 'ILIKE', "%{$courseData['name']}%")
            ->where('tee_name', 'ILIKE', "%{$courseData['tee_name']}%")
            ->first();

        if ($existing) {
            // Increment submission count
            $existing->increment('submission_count');
        } else {
            // Create new unverified course
            UnverifiedCourse::create(array_merge($courseData, [
                'submission_count' => 1,
                'confidence_score' => $data['confidence_score'] ?? 0.0,
                'source_data' => json_encode($data),
            ]));
        }
    }

    /**
     * Create round and scores from scan data
     */
    private function createRoundFromScan(ScorecardScan $scan, int $courseId, array $data): array
    {
        $result = [
            'created' => false,
            'round_id' => null,
            'scores_created' => 0,
            'errors' => [],
        ];

        try {
            DB::beginTransaction();

            // Extract round information
            $playedAt = $this->extractPlayedDate($data);
            $players = $data['players'] ?? [];

            if (empty($players)) {
                $result['errors'][] = 'No players found in scorecard data';
                DB::rollBack();

                return $result;
            }

            // Create round for primary player (first player)
            $primaryPlayer = $players[0];
            $totalScore = $this->extractPlayerTotalScore($data, $primaryPlayer);

            $roundData = [
                'user_id' => $scan->user_id,
                'course_id' => $courseId,
                'scorecard_scan_id' => $scan->id,
                'played_at' => $playedAt,
                'total_score' => $totalScore,
                'front_nine_score' => $this->extractPlayerFrontNineScore($data, $primaryPlayer),
                'back_nine_score' => $this->extractPlayerBackNineScore($data, $primaryPlayer),
                'weather' => $data['weather'] ?? null,
                'notes' => $this->generateRoundNotes($data, $players),
            ];

            $round = Round::create($roundData);
            $result['created'] = true;
            $result['round_id'] = $round->id;

            // Create individual hole scores for all players
            $scoresCreated = $this->createHoleScores($round, $data, $players);
            $result['scores_created'] = $scoresCreated;

            DB::commit();

            Log::info('Created round from scorecard scan', [
                'round_id' => $round->id,
                'scan_id' => $scan->id,
                'course_id' => $courseId,
                'players' => count($players),
                'scores_created' => $scoresCreated,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $result['errors'][] = 'Failed to create round: '.$e->getMessage();

            Log::error('Failed to create round from scan', [
                'scan_id' => $scan->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Create individual hole scores for all players
     */
    private function createHoleScores(Round $round, array $data, array $players): int
    {
        $scoresCreated = 0;
        $course = $round->course;
        $parValues = $course->par_values ?? [];
        $handicapValues = $course->handicap_values ?? [];

        foreach ($players as $player) {
            $playerScores = $this->extractPlayerHoleScores($data, $player);

            if (empty($playerScores)) {
                continue; // Skip if no hole scores found for this player
            }

            for ($hole = 1; $hole <= 18; $hole++) {
                if (! isset($playerScores[$hole - 1])) {
                    continue; // Skip missing holes
                }

                $scoreData = [
                    'round_id' => $round->id,
                    'player_name' => $player,
                    'hole_number' => $hole,
                    'score' => (int) $playerScores[$hole - 1],
                    'par' => $parValues[$hole - 1] ?? 4,
                    'handicap' => $handicapValues[$hole - 1] ?? $hole,
                ];

                RoundScore::create($scoreData);
                $scoresCreated++;
            }
        }

        return $scoresCreated;
    }

    // Data extraction helper methods
    private function extractParValues(array $data): array
    {
        return $data['par_values'] ?? [];
    }

    private function extractHandicapValues(array $data): array
    {
        return $data['handicap_values'] ?? [];
    }

    private function extractSlope(array $data): ?int
    {
        return isset($data['slope_rating']) ? (int) $data['slope_rating'] : null;
    }

    private function extractRating(array $data): ?float
    {
        return isset($data['course_rating']) ? (float) $data['course_rating'] : null;
    }

    private function extractLocation(array $data): ?string
    {
        return $data['course_location'] ?? $data['location'] ?? null;
    }

    private function extractPlayedDate(array $data): ?\DateTime
    {
        $dateStr = $data['date'] ?? $data['date_played'] ?? null;

        if (! $dateStr) {
            return now()->toDate();
        }

        try {
            return new \DateTime($dateStr);
        } catch (\Exception $e) {
            return now()->toDate();
        }
    }

    private function extractPlayerTotalScore(array $data, string $player): ?int
    {
        if (isset($data['player_scores'][$player]['total_score'])) {
            return (int) $data['player_scores'][$player]['total_score'];
        }

        // Calculate from hole scores if available
        $holeScores = $this->extractPlayerHoleScores($data, $player);

        return ! empty($holeScores) ? array_sum($holeScores) : null;
    }

    private function extractPlayerFrontNineScore(array $data, string $player): ?int
    {
        if (isset($data['player_scores'][$player]['front_nine_score'])) {
            return (int) $data['player_scores'][$player]['front_nine_score'];
        }

        $holeScores = $this->extractPlayerHoleScores($data, $player);

        return ! empty($holeScores) ? array_sum(array_slice($holeScores, 0, 9)) : null;
    }

    private function extractPlayerBackNineScore(array $data, string $player): ?int
    {
        if (isset($data['player_scores'][$player]['back_nine_score'])) {
            return (int) $data['player_scores'][$player]['back_nine_score'];
        }

        $holeScores = $this->extractPlayerHoleScores($data, $player);

        return ! empty($holeScores) && count($holeScores) >= 18 ?
            array_sum(array_slice($holeScores, 9, 9)) : null;
    }

    private function extractPlayerHoleScores(array $data, string $player): array
    {
        if (isset($data['player_scores'][$player]['hole_scores'])) {
            return $data['player_scores'][$player]['hole_scores'];
        }

        return [];
    }

    private function generateRoundNotes(array $data, array $players): ?string
    {
        $notes = [];

        if (count($players) > 1) {
            $notes[] = 'Played with: '.implode(', ', array_slice($players, 1));
        }

        if (! empty($data['weather'])) {
            $notes[] = 'Weather: '.$data['weather'];
        }

        if (! empty($data['additional_notes'])) {
            $notes[] = $data['additional_notes'];
        }

        return ! empty($notes) ? implode('. ', $notes) : null;
    }
}
