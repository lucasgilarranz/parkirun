<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Run;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $year = now()->year;

        $runnerA = User::factory()->create([
            'name' => 'Runner One',
            'email' => 'runner1@example.com',
        ]);

        $runnerB = User::factory()->create([
            'name' => 'Runner Two',
            'email' => 'runner2@example.com',
        ]);

        Goal::factory()->for($runnerA)->create([
            'open_season_target_km' => 120,
            'closed_season_target_km' => 160,
        ]);

        Goal::factory()->for($runnerB)->create([
            'open_season_target_km' => 180,
            'closed_season_target_km' => 140,
        ]);

        $openSeasonDates = [
            CarbonImmutable::create($year, 1, 6),
            CarbonImmutable::create($year, 2, 12),
            CarbonImmutable::create($year, 3, 9),
            CarbonImmutable::create($year, 4, 20),
            CarbonImmutable::create($year, 6, 1),
        ];

        $closedSeasonDates = [
            CarbonImmutable::create($year, 7, 3),
            CarbonImmutable::create($year, 8, 14),
            CarbonImmutable::create($year, 9, 5),
            CarbonImmutable::create($year, 10, 19),
            CarbonImmutable::create($year, 12, 8),
        ];

        foreach ($openSeasonDates as $index => $date) {
            Run::factory()->for($runnerA)->create([
                'date' => $date->toDateString(),
                'distance_km' => 6 + $index,
            ]);

            Run::factory()->for($runnerB)->create([
                'date' => $date->addDays(2)->toDateString(),
                'distance_km' => 5 + $index * 1.5,
            ]);
        }

        foreach ($closedSeasonDates as $index => $date) {
            Run::factory()->for($runnerA)->create([
                'date' => $date->toDateString(),
                'distance_km' => 7 + $index,
            ]);

            Run::factory()->for($runnerB)->create([
                'date' => $date->addDays(1)->toDateString(),
                'distance_km' => 6 + $index * 1.4,
            ]);
        }
    }
}
