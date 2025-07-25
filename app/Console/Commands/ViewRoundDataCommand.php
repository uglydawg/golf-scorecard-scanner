<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ScorecardScanner\Models\Course;
use ScorecardScanner\Models\Round;

class ViewRoundDataCommand extends Command
{
    protected $signature = 'scorecard:view-round 
                          {round-id : The round ID to view}
                          {--scores : Show individual hole scores}
                          {--course : Show course details}';

    protected $description = 'View detailed round data from converted scorecard scans';

    public function handle(): int
    {
        $roundId = (int) $this->argument('round-id');
        $showScores = $this->option('scores');
        $showCourse = $this->option('course');

        $round = Round::with(['course', 'user', 'scorecardScan', 'scores'])
            ->find($roundId);

        if (! $round) {
            $this->error("Round with ID {$roundId} not found");

            return 1;
        }

        $this->info('🏌️ Golf Round Details');
        $this->info('====================');

        // Round Information
        $this->displayRoundInfo($round);

        // Course Information
        if ($showCourse || ! $showScores) {
            $this->displayCourseInfo($round->course);
        }

        // Individual Hole Scores
        if ($showScores) {
            $this->displayHoleScores($round);
        }

        return 0;
    }

    private function displayRoundInfo(Round $round): void
    {
        $this->info("\n📊 Round Information:");

        $roundData = [
            ['Round ID', $round->id],
            ['Course', $round->course->name.' ('.$round->course->tee_name.' Tees)'],
            ['Player', $round->user->name ?? 'User ID: '.$round->user_id],
            ['Date Played', $round->played_at->format('F j, Y')],
            ['Total Score', $round->total_score],
            ['Score vs Par', $this->getScoreVsPar($round)],
            ['Front Nine', $round->front_nine_score ?? 'N/A'],
            ['Back Nine', $round->back_nine_score ?? 'N/A'],
            ['Weather', $round->weather ?: 'Not recorded'],
            ['Notes', $round->notes ?: 'None'],
        ];

        $this->table(['Property', 'Value'], $roundData);
    }

    private function displayCourseInfo(Course $course): void
    {
        $this->info("\n⛳ Course Information:");

        $parValues = $course->par_values ?? [];
        $totalPar = ! empty($parValues) ? array_sum($parValues) : 'N/A';
        $frontNinePar = ! empty($parValues) ? array_sum(array_slice($parValues, 0, 9)) : 'N/A';
        $backNinePar = ! empty($parValues) && count($parValues) >= 18 ?
            array_sum(array_slice($parValues, 9, 9)) : 'N/A';

        $courseData = [
            ['Course Name', $course->name],
            ['Tee Name', $course->tee_name],
            ['Location', $course->location ?: 'Not specified'],
            ['Course Rating', $course->rating ?: 'N/A'],
            ['Slope Rating', $course->slope ?: 'N/A'],
            ['Total Par', $totalPar],
            ['Front Nine Par', $frontNinePar],
            ['Back Nine Par', $backNinePar],
            ['Verified', $course->is_verified ? 'Yes' : 'No'],
        ];

        $this->table(['Property', 'Value'], $courseData);

        // Show par values if available
        if (! empty($parValues) && count($parValues) === 18) {
            $this->info("\n🏌️ Hole Par Values:");
            $this->line('Out: '.implode('-', array_slice($parValues, 0, 9))." = {$frontNinePar}");
            $this->line('In:  '.implode('-', array_slice($parValues, 9, 9))." = {$backNinePar}");
            $this->line("Total: {$totalPar}");
        }
    }

    private function displayHoleScores(Round $round): void
    {
        $scores = $round->scores()->orderBy('player_name')->orderBy('hole_number')->get();

        if ($scores->isEmpty()) {
            $this->warn('No individual hole scores recorded for this round');

            return;
        }

        $this->info("\n📊 Individual Hole Scores:");

        // Group scores by player
        $playerScores = $scores->groupBy('player_name');

        foreach ($playerScores as $playerName => $playerHoles) {
            $this->info("\n👤 {$playerName}:");

            $frontNine = $playerHoles->where('hole_number', '<=', 9)->sortBy('hole_number');
            $backNine = $playerHoles->where('hole_number', '>', 9)->sortBy('hole_number');

            // Front Nine
            $frontHoles = $frontNine->pluck('hole_number')->toArray();
            $frontScores = $frontNine->pluck('score')->toArray();
            $frontPars = $frontNine->pluck('par')->toArray();
            $frontTotal = array_sum($frontScores);

            if (! empty($frontScores)) {
                $this->line('Holes: '.implode('  ', array_map(fn ($h) => str_pad((string) $h, 2), $frontHoles)));
                $this->line('Par:   '.implode('  ', array_map(fn ($p) => str_pad((string) $p, 2), $frontPars)));
                $this->line('Score: '.implode('  ', array_map(fn ($s) => str_pad((string) $s, 2), $frontScores))." = {$frontTotal}");
            }

            // Back Nine
            $backHoles = $backNine->pluck('hole_number')->toArray();
            $backScores = $backNine->pluck('score')->toArray();
            $backPars = $backNine->pluck('par')->toArray();
            $backTotal = array_sum($backScores);

            if (! empty($backScores)) {
                $this->line('Holes: '.implode('  ', array_map(fn ($h) => str_pad((string) $h, 2), $backHoles)));
                $this->line('Par:   '.implode('  ', array_map(fn ($p) => str_pad((string) $p, 2), $backPars)));
                $this->line('Score: '.implode('  ', array_map(fn ($s) => str_pad((string) $s, 2), $backScores))." = {$backTotal}");
            }

            $totalScore = $frontTotal + $backTotal;
            $totalPar = array_sum($frontPars) + array_sum($backPars);
            $scoreVsPar = $totalScore - $totalPar;
            $scoreVsParText = $scoreVsPar > 0 ? "+{$scoreVsPar}" : ($scoreVsPar < 0 ? "{$scoreVsPar}" : 'E');

            $this->line("Total: {$totalScore} ({$scoreVsParText})");

            // Show notable holes
            $this->showNotableHoles($playerHoles);
        }
    }

    private function showNotableHoles($playerHoles): void
    {
        $eagles = $playerHoles->filter(fn ($h) => $h->score <= $h->par - 2);
        $birdies = $playerHoles->filter(fn ($h) => $h->score === $h->par - 1);
        $bogeys = $playerHoles->filter(fn ($h) => $h->score === $h->par + 1);
        $doubleBogeys = $playerHoles->filter(fn ($h) => $h->score >= $h->par + 2);

        $notable = [];
        if ($eagles->count() > 0) {
            $holes = $eagles->pluck('hole_number')->join(', ');
            $notable[] = "🦅 Eagles: {$holes}";
        }
        if ($birdies->count() > 0) {
            $holes = $birdies->pluck('hole_number')->join(', ');
            $notable[] = "🐦 Birdies: {$holes}";
        }
        if ($bogeys->count() > 0) {
            $holes = $bogeys->pluck('hole_number')->join(', ');
            $notable[] = "😔 Bogeys: {$holes}";
        }
        if ($doubleBogeys->count() > 0) {
            $holes = $doubleBogeys->pluck('hole_number')->join(', ');
            $notable[] = "😖 Double+: {$holes}";
        }

        if (! empty($notable)) {
            $this->line(implode(', ', $notable));
        }
    }

    private function getScoreVsPar(Round $round): string
    {
        $coursePar = $round->course->par_values ? array_sum($round->course->par_values) : null;

        if (! $coursePar || ! $round->total_score) {
            return 'N/A';
        }

        $diff = $round->total_score - $coursePar;

        if ($diff === 0) {
            return 'Even Par';
        }
        if ($diff > 0) {
            return "+{$diff}";
        }

        return (string) $diff;
    }
}
