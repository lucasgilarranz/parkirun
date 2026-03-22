<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_non_owner_cannot_update_another_users_run_outside_filament(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        $this->actingAs($other);

        $this->assertFalse($other->can('update', $run));
    }

    public function test_owner_can_update_own_run_outside_filament(): void
    {
        $owner = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        $this->actingAs($owner);

        $this->assertTrue($owner->can('update', $run));
    }

    public function test_non_owner_can_update_another_users_run_when_filament_panel_is_active(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        Filament::setCurrentPanel('admin');

        $this->actingAs($other);

        $this->assertTrue($other->can('update', $run));
    }

    public function test_non_owner_cannot_delete_another_users_run_outside_filament(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        $this->actingAs($other);

        $this->assertFalse($other->can('delete', $run));
    }

    public function test_non_owner_can_delete_another_users_run_when_filament_panel_is_active(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $run = Run::factory()->for($owner)->create();

        Filament::setCurrentPanel('admin');

        $this->actingAs($other);

        $this->assertTrue($other->can('delete', $run));
    }
}
