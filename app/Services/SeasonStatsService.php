<?php

namespace App\Services;

use App\Enums\Season;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SeasonStatsService
{
    public function currentSeason(?CarbonImmutable $date = null): Season
    {
        $date = $date ?? CarbonImmutable::now();

        return $date->month <= 6 ? Season::OpenSeason : Season::ClosedSeason;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function seasonRange(Season $season, int $year): array
    {
        return [
            'start' => $season->startDate($year)->startOfDay(),
            'end' => $season->endDate($year)->endOfDay(),
        ];
    }

    /**
     * @return array{
     *     season: Season,
     *     year: int,
     *     range: array{start: CarbonImmutable, end: CarbonImmutable},
     *     players: array<int, array{
     *         id: int,
     *         name: string,
     *         total_km: float,
     *         target_km: float,
     *         completion_percentage: float,
     *         runs_count: int,
     *         active_days: int,
     *         avg_distance: float,
     *         longest_run: float,
     *         longest_streak_days: int,
     *         training_density_km_per_week: float,
     *         training_density_percent: float,
     *         consistency_score: float,
     *         total_distance_score: float,
     *         avg_distance_score: float,
     *         longest_run_score: float,
     *         streak_score: float,
     *         training_density_score: float,
     *         rank: int
     *     }>,
     *     radar: array{
     *         labels: list<string>,
     *         datasets: array<int, array{
     *             label: string,
     *             data: list<float>
     *         }>
     *     }
     * }
     */
    public function seasonStats(Season $season, int $year): array
    {
        $range = $this->seasonRange($season, $year);

        $users = User::query()
            ->with([
                'goal',
                'runs' => fn ($query) => $query->whereBetween('date', [$range['start'], $range['end']]),
            ])
            ->get();

        $raw = $users->map(fn (User $user) => $this->rawStatsForUser($user, $season, $year, $range));

        $maxActiveDays = $raw->max('active_days') ?? 0;
        $maxStreak = $raw->max('longest_streak_days') ?? 0;
        $maxTotalPercent = $raw->max(fn (array $stats) => $this->percentOfTargetRaw($stats['total_km'], $stats['target_km'])) ?? 0;
        $maxAvgPercent = $raw->max(fn (array $stats) => $this->percentOfTargetRaw($stats['avg_distance'], $stats['target_km'])) ?? 0;
        $maxLongestPercent = $raw->max(fn (array $stats) => $this->percentOfTargetRaw($stats['longest_run'], $stats['target_km'])) ?? 0;
        $maxTrainingDensityPercent = $raw->max('training_density_percent') ?? 0;

        $players = $raw->map(function (array $stats) use (
            $maxActiveDays,
            $maxStreak,
            $maxTotalPercent,
            $maxAvgPercent,
            $maxLongestPercent,
            $maxTrainingDensityPercent,
        ) {
            $stats['consistency_score'] = $this->normalize($stats['active_days'], $maxActiveDays);
            $stats['total_distance_score'] = $this->normalize(
                $this->percentOfTargetRaw($stats['total_km'], $stats['target_km']),
                $maxTotalPercent,
            );
            $stats['avg_distance_score'] = $this->normalize(
                $this->percentOfTargetRaw($stats['avg_distance'], $stats['target_km']),
                $maxAvgPercent,
            );
            $stats['longest_run_score'] = $this->normalize(
                $this->percentOfTargetRaw($stats['longest_run'], $stats['target_km']),
                $maxLongestPercent,
            );
            $stats['streak_score'] = $this->normalize($stats['longest_streak_days'], $maxStreak);
            $stats['training_density_score'] = $this->normalize(
                $stats['training_density_percent'],
                $maxTrainingDensityPercent,
            );

            return $stats;
        });

        $leaderboard = $players->sortByDesc('completion_percentage')->values();
        $leaderboard = $leaderboard->map(function (array $stats, int $index) {
            $stats['rank'] = $index + 1;

            return $stats;
        });

        $radarLabels = [
            'Consistency',
            'Total Distance',
            'Average Distance',
            'Longest Run',
            'Streak',
            'Training Density',
        ];

        $radarDatasets = $leaderboard->map(fn (array $stats) => [
            'label' => $stats['name'],
            'data' => [
                $stats['consistency_score'],
                $stats['total_distance_score'],
                $stats['avg_distance_score'],
                $stats['longest_run_score'],
                $stats['streak_score'],
                $stats['training_density_score'],
            ],
        ])->values()->all();

        return [
            'season' => $season,
            'year' => $year,
            'range' => $range,
            'season_progress' => round($this->seasonProgress($range['start'], $range['end']), 2),
            'players' => $leaderboard->values()->all(),
            'radar' => [
                'labels' => $radarLabels,
                'datasets' => $radarDatasets,
            ],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     total_km: float,
     *     target_km: float,
     *     completion_percentage: float,
     *     runs_count: int,
     *     active_days: int,
     *     avg_distance: float,
     *     longest_run: float,
     *     longest_streak_days: int,
     *     training_density_km_per_week: float,
     *     training_density_percent: float
     * }
     */
    private function rawStatsForUser(User $user, Season $season, int $year, array $range): array
    {
        $runs = $user->runs;
        $totalKm = (float) $runs->sum('distance_km');
        $runsCount = $runs->count();
        $avgDistance = $runsCount > 0 ? $totalKm / $runsCount : 0.0;
        $longestRun = (float) ($runs->max('distance_km') ?? 0);

        $activeDays = $runs->pluck('date')
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();

        $longestStreak = $this->longestStreakDays($activeDays);
        $targetField = $season->targetField();
        $targetKm = (float) ($user->goal?->{$targetField} ?? 0);
        $completion = $targetKm > 0 ? $totalKm / $targetKm : 0.0;
        $totalWeeks = $this->totalWeeksInSeason($season, $year);
        $weeklyGoal = $totalWeeks > 0 ? $targetKm / $totalWeeks : 0.0;
        $weeklyKm = $totalWeeks > 0 ? $totalKm / $totalWeeks : 0.0;
        $trainingDensityPercent = $weeklyGoal > 0 ? ($weeklyKm / $weeklyGoal) * 100 : 0.0;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'total_km' => round($totalKm, 2),
            'target_km' => round($targetKm, 2),
            'completion_percentage' => round($completion * 100, 2),
            'runs_count' => $runsCount,
            'active_days' => $activeDays->count(),
            'avg_distance' => round($avgDistance, 2),
            'longest_run' => round($longestRun, 2),
            'longest_streak_days' => $longestStreak,
            'training_density_km_per_week' => round($weeklyKm, 2),
            'training_density_percent' => round($trainingDensityPercent, 2),
        ];
    }

    private function normalize(float $value, float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }

        return round(($value / $max) * 100, 2);
    }

    private function percentOfTargetRaw(float $value, float $target): float
    {
        if ($target <= 0) {
            return 0.0;
        }

        return ($value / $target) * 100;
    }

    /**
     * @param  Collection<int, mixed>  $activeDays
     */
    private function longestStreakDays(Collection $activeDays): int
    {
        if ($activeDays->isEmpty()) {
            return 0;
        }

        $dates = $activeDays
            ->map(fn (string $date) => CarbonImmutable::parse($date))
            ->sort()
            ->values();

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            $diff = $dates[$i - 1]->diffInDays($dates[$i]);

            if ($diff <= 2) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    private function totalWeeksInSeason(Season $season, int $year): int
    {
        $range = $this->seasonRange($season, $year);
        $totalWeeks = $range['start']
            ->startOfWeek()
            ->diffInWeeks($range['end']->startOfWeek()) + 1;

        return max(0, $totalWeeks);
    }

    private function seasonProgress(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        $now = CarbonImmutable::now();

        if ($now->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return 100.0;
        }

        $elapsed = $start->diffInSeconds($now);
        $total = $start->diffInSeconds($end);

        if ($total <= 0) {
            return 0.0;
        }

        return ($elapsed / $total) * 100;
    }
}
