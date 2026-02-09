<?php

namespace Tests\Feature;

use App\Enums\Season;
use App\Models\Goal;
use App\Models\Run;
use App\Models\User;
use App\Services\SeasonStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SeasonStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_is_sorted_by_completion_percentage(): void
    {
        $userA = User::factory()->create(['name' => 'Runner A']);
        $userB = User::factory()->create(['name' => 'Runner B']);

        Goal::factory()->for($userA)->create([
            'open_season_target_km' => 100,
            'closed_season_target_km' => 100,
        ]);
        Goal::factory()->for($userB)->create([
            'open_season_target_km' => 200,
            'closed_season_target_km' => 200,
        ]);

        Run::factory()->for($userA)->create([
            'date' => '2026-02-10',
            'distance_km' => 50,
        ]);
        Run::factory()->for($userB)->create([
            'date' => '2026-02-10',
            'distance_km' => 50,
        ]);

        $stats = app(SeasonStatsService::class)
            ->seasonStats(Season::OpenSeason, 2026);

        $this->assertSame('Runner A', $stats['players'][0]['name']);
        $this->assertSame(50.0, $stats['players'][0]['completion_percentage']);
        $this->assertSame('Runner B', $stats['players'][1]['name']);
        $this->assertSame(25.0, $stats['players'][1]['completion_percentage']);
    }

    public function test_season_boundaries_are_respected(): void
    {
        $user = User::factory()->create();
        Goal::factory()->for($user)->create([
            'open_season_target_km' => 100,
            'closed_season_target_km' => 100,
        ]);

        Run::factory()->for($user)->create([
            'date' => '2026-06-30',
            'distance_km' => 10,
        ]);
        Run::factory()->for($user)->create([
            'date' => '2026-07-01',
            'distance_km' => 12,
        ]);

        $service = app(SeasonStatsService::class);

        $openStats = $service->seasonStats(Season::OpenSeason, 2026);
        $closedStats = $service->seasonStats(Season::ClosedSeason, 2026);

        $this->assertSame(10.0, $openStats['players'][0]['total_km']);
        $this->assertSame(12.0, $closedStats['players'][0]['total_km']);
    }

    public function test_run_policy_allows_only_owner_to_update(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $run));
        $this->assertFalse(Gate::forUser($other)->allows('update', $run));
    }
}
