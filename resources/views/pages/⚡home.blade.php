<?php

use App\Services\SeasonStatsService;
use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.public')] class extends Component
{
    public string $seasonLabel = '';
    public string $seasonRange = '';
    public array $players = [];
    public array $barChart = [];
    public array $radarChart = [];

    public function mount(SeasonStatsService $statsService): void
    {
        $season = $statsService->currentSeason();
        $year = now()->year;

        $stats = $statsService->seasonStats($season, $year);

        $this->seasonLabel = $stats['season']->label();
        $this->seasonRange = $stats['range']['start']->format('M j').' - '.$stats['range']['end']->format('M j');
        $this->players = $stats['players'];

        $this->barChart = [
            'labels' => collect($stats['players'])->pluck('name')->values()->all(),
            'datasets' => [
                [
                    'label' => 'Completion %',
                    'data' => collect($stats['players'])->pluck('completion_percentage')->values()->all(),
                    'backgroundColor' => ['#0f766e', '#2563eb'],
                    'borderRadius' => 999,
                    'barThickness' => 14,
                ],
            ],
            'targetLine' => [
                'value' => $stats['season_progress'],
                'color' => '#facc15',
                'width' => 2,
            ],
        ];

        $this->radarChart = [
            'labels' => $stats['radar']['labels'],
            'datasets' => collect($stats['radar']['datasets'])->map(function (array $dataset, int $index) {
                $palette = [
                    ['#14b8a6', 'rgba(20, 184, 166, 0.2)'],
                    ['#3b82f6', 'rgba(59, 130, 246, 0.2)'],
                ];
                $colors = $palette[$index] ?? ['#64748b', 'rgba(100, 116, 139, 0.2)'];

                return [
                    'label' => $dataset['label'],
                    'data' => $dataset['data'],
                    'borderColor' => $colors[0],
                    'backgroundColor' => $colors[1],
                    'pointBackgroundColor' => $colors[0],
                ];
            })->values()->all(),
        ];
    }
};
?>

<div class="flex flex-col gap-6">
        <flux:card
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            x-data="{
                deferredPrompt: null,
                standalone () {
                    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                },
                init () {
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.deferredPrompt = e;
                    });
                },
                async installOrHelp () {
                    if (this.standalone()) {
                        return;
                    }
                    if (this.deferredPrompt) {
                        this.deferredPrompt.prompt();
                        await this.deferredPrompt.userChoice;
                        this.deferredPrompt = null;

                        return;
                    }
                    this.$dispatch('open-modal', 'install-pwa');
                },
            }"
            x-show="!standalone()"
            x-cloak
        >
            <div>
                <flux:heading size="sm">Add to your home screen</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">Open ParkiRun from your phone like a regular app.</flux:text>
            </div>
            <flux:button icon="arrow-down-tray" x-on:click="installOrHelp()">
                Add to home screen
            </flux:button>
        </flux:card>

        <flux:modal name="install-pwa" class="max-w-md">
            <flux:heading size="lg">Add to home screen</flux:heading>
            <div class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                <p>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">iPhone or iPad:</span>
                    tap the Share button <span class="whitespace-nowrap">(square with an arrow)</span>, then
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Add to Home Screen</span>.
                </p>
                <p>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Android:</span>
                    open the browser menu (⋮), then choose
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Install app</span>
                    or
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">Add to Home screen</span>.
                </p>
            </div>
            <div class="mt-6 flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">Got it</flux:button>
                </flux:modal.close>
            </div>
        </flux:modal>

        <flux:card class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">Current Season</flux:heading>
                    <flux:subheading>{{ $seasonLabel }} · {{ $seasonRange }}</flux:subheading>
                </div>
                @if (count($players) > 0)
                    <flux:badge variant="success">
                        Leader: {{ $players[0]['name'] }}
                    </flux:badge>
                @endif
            </div>

            <div class="h-44">
                <div
                    wire:ignore
                    x-data="chart({ type: 'bar', data: @js($barChart), options: { indexAxis: 'y', scales: { x: { min: 0, max: 100 } } } })"
                    x-init="init()"
                    class="h-full"
                >
                    <canvas x-ref="canvas" class="h-full w-full"></canvas>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">Season Leaderboard</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                @forelse ($players as $player)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:heading size="sm">{{ $player['name'] }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500">Rank {{ $player['rank'] }}</flux:text>
                            </div>
                            <flux:badge variant="{{ $player['rank'] === 1 ? 'success' : 'secondary' }}">
                                {{ $player['completion_percentage'] }}%
                            </flux:badge>
                        </div>
                        <div class="mt-3 space-y-1 text-sm">
                            <div class="flex items-center justify-between">
                                <span>Total km</span>
                                <span class="font-semibold">{{ $player['total_km'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Target km</span>
                                <span class="font-semibold">{{ $player['target_km'] }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <flux:text>No runners yet.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Performance Radar</flux:heading>
                <flux:text class="text-sm text-zinc-500">0–100 normalized scores</flux:text>
            </div>
            <div class="h-72">
                <div
                    wire:ignore
                    x-data="chart({ type: 'radar', data: @js($radarChart), options: { scales: { r: { min: 0, max: 100, ticks: { display: false }, grid: { color: 'rgba(148, 163, 184, 0.2)' }, angleLines: { color: 'rgba(148, 163, 184, 0.5)' } } } } })"
                    x-init="init()"
                    class="h-full"
                >
                    <canvas x-ref="canvas" class="h-full w-full"></canvas>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">Advanced Stats</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($players as $player)
                    <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
                        <div class="mb-2 flex items-center justify-between">
                            <flux:heading size="sm">{{ $player['name'] }}</flux:heading>
                            <flux:text class="text-xs text-zinc-500">Season stats</flux:text>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span>Consistency (active days)</span>
                                <span class="font-semibold">{{ $player['active_days'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Longest run</span>
                                <span class="font-semibold">{{ $player['longest_run'] }} km</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Average distance</span>
                                <span class="font-semibold">{{ $player['avg_distance'] }} km</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Longest streak (2dg)</span>
                                <span class="font-semibold">{{ $player['longest_streak_days'] }} days</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Training density</span>
                                <span class="font-semibold">{{ $player['training_density_percent'] }}%</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>