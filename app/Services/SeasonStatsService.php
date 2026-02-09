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
     *         active_weeks: int,
     *         activity_spread_score: float,
     *         consistency_score: float,
     *         total_distance_score: float,
     *         avg_distance_score: float,
     *         longest_run_score: float,
     *         streak_score: float,
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
        $maxTotalKm = $raw->max('total_km') ?? 0;
        $maxAvgDistance = $raw->max('avg_distance') ?? 0;
        $maxLongestRun = $raw->max('longest_run') ?? 0;
        $maxStreak = $raw->max('longest_streak_days') ?? 0;

        $players = $raw->map(function (array $stats) use (
            $maxActiveDays,
            $maxTotalKm,
            $maxAvgDistance,
            $maxLongestRun,
            $maxStreak,
        ) {
            $stats['consistency_score'] = $this->normalize($stats['active_days'], $maxActiveDays);
            $stats['total_distance_score'] = $this->normalize($stats['total_km'], $maxTotalKm);
            $stats['avg_distance_score'] = $this->normalize($stats['avg_distance'], $maxAvgDistance);
            $stats['longest_run_score'] = $this->normalize($stats['longest_run'], $maxLongestRun);
            $stats['streak_score'] = $this->normalize($stats['longest_streak_days'], $maxStreak);

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
            'Activity Spread',
        ];

        $radarDatasets = $leaderboard->map(fn (array $stats) => [
            'label' => $stats['name'],
            'data' => [
                $stats['consistency_score'],
                $stats['total_distance_score'],
                $stats['avg_distance_score'],
                $stats['longest_run_score'],
                $stats['streak_score'],
                $stats['activity_spread_score'],
            ],
        ])->values()->all();

        return [
            'season' => $season,
            'year' => $year,
            'range' => $range,
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
     *     active_weeks: int,
     *     activity_spread_score: float
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
        $activeWeeks = $this->activeWeeksCount($runs);
        $activitySpread = $this->activitySpreadScore($activeWeeks, $season, $year);

        $targetField = $season->targetField();
        $targetKm = (float) ($user->goal?->{$targetField} ?? 0);
        $completion = $targetKm > 0 ? $totalKm / $targetKm : 0.0;

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
            'active_weeks' => $activeWeeks,
            'activity_spread_score' => round($activitySpread, 2),
        ];
    }

    private function normalize(float $value, float $max): float
    {
        if ($max <= 0) {
            return 0.0;
        }

        return round(($value / $max) * 100, 2);
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

            if ($diff === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    /**
     * @param  Collection<int, \App\Models\Run>  $runs
     */
    private function activeWeeksCount(Collection $runs): int
    {
        return $runs
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date)->startOfWeek()->toDateString())
            ->unique()
            ->count();
    }

    private function activitySpreadScore(int $activeWeeks, Season $season, int $year): float
    {
        $range = $this->seasonRange($season, $year);
        $totalWeeks = $range['start']
            ->startOfWeek()
            ->diffInWeeks($range['end']->startOfWeek()) + 1;

        if ($totalWeeks <= 0) {
            return 0.0;
        }

        return ($activeWeeks / $totalWeeks) * 100;
    }
}
