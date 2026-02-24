<?php

use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.public')] class extends Component
{
};
?>

<div class="flex flex-col gap-6">
    <flux:card class="space-y-3">
        <flux:heading size="lg">About ParkiRun</flux:heading>
        <flux:text>
            ParkiRun is a season-long head-to-head running challenge for two runners. Each season is scored
            independently, so progress resets when the new season begins.
        </flux:text>
    </flux:card>

    <flux:card class="space-y-3">
        <flux:heading size="lg">How the game works</flux:heading>
        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
            <p>Each runner sets a target distance for the season.</p>
            <p>Runs are logged manually and only count toward the season they occur in.</p>
            <p>The leaderboard is based on completion percentage, not raw distance.</p>
        </div>
    </flux:card>

    <flux:card class="space-y-3">
        <flux:heading size="lg">Stats breakdown</flux:heading>
        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
            <p><strong>Consistency</strong> shows how regularly each runner logs runs.</p>
            <p><strong>Total Distance</strong> reflects overall progress against the season goal.</p>
            <p><strong>Average Distance</strong> highlights typical effort per run.</p>
            <p><strong>Longest Run</strong> captures each runner’s peak distance.</p>
            <p><strong>Streak (2dg)</strong> tracks consistent training with a short 2-day gap (2dg) allowed.</p>
            <p><strong>Training Density</strong> compares weekly distance to each runner’s goal pace.</p>
        </div>
    </flux:card>
</div>
