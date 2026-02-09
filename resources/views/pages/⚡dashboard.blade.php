<?php

use App\Enums\Season;
use App\Models\Goal;
use App\Models\Run;
use App\Services\SeasonStatsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.app')] class extends Component
{
    public string $seasonKey = 'open';
    public int $seasonYear = 0;
    public string $seasonLabel = '';
    public string $seasonRange = '';

    public array $players = [];
    public array $currentPlayer = [];
    public array $barChart = [];

    public string $runDate = '';
    public string $runDistance = '';

    public ?int $editingRunId = null;
    public string $editingDate = '';
    public string $editingDistance = '';

    public string $openSeasonTarget = '';
    public string $closedSeasonTarget = '';

    public array $runs = [];

    public function mount(SeasonStatsService $statsService): void
    {
        $season = $statsService->currentSeason();
        $this->seasonKey = $season === Season::OpenSeason ? 'open' : 'closed';
        $this->seasonYear = now()->year;

        $this->refreshData($statsService);
    }

    public function createRun(SeasonStatsService $statsService): void
    {
        $validated = $this->validate([
            'runDate' => ['required', 'date'],
            'runDistance' => ['required', 'numeric', 'min:0.1'],
        ]);

        Gate::authorize('create', Run::class);

        Auth::user()->runs()->create([
            'date' => $validated['runDate'],
            'distance_km' => $validated['runDistance'],
        ]);

        $this->reset(['runDate', 'runDistance']);
        $this->refreshData($statsService);
    }

    public function startEditing(int $runId): void
    {
        $run = Run::query()->findOrFail($runId);
        Gate::authorize('update', $run);

        $this->editingRunId = $run->id;
        $this->editingDate = $run->date?->format('Y-m-d') ?? '';
        $this->editingDistance = (string) $run->distance_km;
    }

    public function updateRun(SeasonStatsService $statsService): void
    {
        if ($this->editingRunId === null) {
            return;
        }

        $validated = $this->validate([
            'editingDate' => ['required', 'date'],
            'editingDistance' => ['required', 'numeric', 'min:0.1'],
        ]);

        $run = Run::query()->findOrFail($this->editingRunId);
        Gate::authorize('update', $run);

        $run->update([
            'date' => $validated['editingDate'],
            'distance_km' => $validated['editingDistance'],
        ]);

        $this->cancelEditing();
        $this->refreshData($statsService);
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingRunId', 'editingDate', 'editingDistance']);
    }

    public function deleteRun(int $runId, SeasonStatsService $statsService): void
    {
        $run = Run::query()->findOrFail($runId);
        Gate::authorize('delete', $run);

        $run->delete();

        $this->refreshData($statsService);
    }

    public function updateGoals(SeasonStatsService $statsService): void
    {
        $validated = $this->validate([
            'openSeasonTarget' => ['required', 'numeric', 'min:0'],
            'closedSeasonTarget' => ['required', 'numeric', 'min:0'],
        ]);

        $goal = Auth::user()->goal;

        if ($goal instanceof Goal) {
            $goal->update([
                'open_season_target_km' => $validated['openSeasonTarget'],
                'closed_season_target_km' => $validated['closedSeasonTarget'],
            ]);
        } else {
            Auth::user()->goal()->create([
                'open_season_target_km' => $validated['openSeasonTarget'],
                'closed_season_target_km' => $validated['closedSeasonTarget'],
            ]);
        }

        $this->refreshData($statsService);
    }

    private function refreshData(SeasonStatsService $statsService): void
    {
        $season = $this->season();
        $stats = $statsService->seasonStats($season, $this->seasonYear);

        $this->seasonLabel = $stats['season']->label();
        $this->seasonRange = $stats['range']['start']->format('M j').' - '.$stats['range']['end']->format('M j');

        $this->players = $stats['players'];
        $this->currentPlayer = collect($stats['players'])
            ->firstWhere('id', Auth::id()) ?? [];

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
        ];

        $this->runs = $this->loadRuns($stats['range']['start'], $stats['range']['end']);
        $this->loadGoals();
    }

    private function loadRuns($start, $end): array
    {
        return Run::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->get()
            ->map(fn (Run $run) => [
                'id' => $run->id,
                'date' => $run->date?->format('Y-m-d') ?? '',
                'distance_km' => (float) $run->distance_km,
            ])
            ->all();
    }

    private function loadGoals(): void
    {
        $goal = Auth::user()->goal;

        $this->openSeasonTarget = (string) ($goal?->open_season_target_km ?? '0');
        $this->closedSeasonTarget = (string) ($goal?->closed_season_target_km ?? '0');
    }

    private function season(): Season
    {
        return $this->seasonKey === 'open' ? Season::OpenSeason : Season::ClosedSeason;
    }
};
?>

<div class="flex flex-col gap-6">
        <flux:card class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">Season Snapshot</flux:heading>
                    <flux:subheading>{{ $seasonLabel }} · {{ $seasonRange }}</flux:subheading>
                </div>
                @if (! empty($currentPlayer))
                    <flux:badge variant="success">
                        {{ $currentPlayer['completion_percentage'] }}% complete
                    </flux:badge>
                @endif
            </div>
            <div class="h-40">
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

        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">Your Goals</flux:heading>
                <form wire:submit="updateGoals" class="space-y-4">
                    <flux:input
                        type="number"
                        step="0.1"
                        name="openSeasonTarget"
                        label="Open season target (km)"
                        wire:model="openSeasonTarget"
                    />
                    <flux:input
                        type="number"
                        step="0.1"
                        name="closedSeasonTarget"
                        label="Closed season target (km)"
                        wire:model="closedSeasonTarget"
                    />
                    <flux:button variant="primary" type="submit" class="w-full">Save goals</flux:button>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading size="lg">Log a Run</flux:heading>
                <form wire:submit="createRun" class="space-y-4">
                    <flux:input type="date" name="runDate" label="Date" wire:model="runDate" />
                    <flux:input
                        type="number"
                        step="0.1"
                        name="runDistance"
                        label="Distance (km)"
                        wire:model="runDistance"
                    />
                    <flux:button variant="primary" type="submit" class="w-full">Add run</flux:button>
                </form>
            </flux:card>
        </div>

        <flux:card class="space-y-4">
            <flux:heading size="lg">Runs This Season</flux:heading>
            <div class="space-y-3">
                @forelse ($runs as $run)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        @if ($editingRunId === $run['id'])
                            <form wire:submit="updateRun" class="grid gap-3 sm:grid-cols-3">
                                <flux:input type="date" name="editingDate" label="Date" wire:model="editingDate" />
                                <flux:input
                                    type="number"
                                    step="0.1"
                                    name="editingDistance"
                                    label="Distance (km)"
                                    wire:model="editingDistance"
                                />
                                <div class="flex items-end gap-2">
                                    <flux:button variant="primary" type="submit" class="w-full">Save</flux:button>
                                    <flux:button variant="subtle" type="button" wire:click="cancelEditing" class="w-full">
                                        Cancel
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                                <div>
                                    <flux:heading size="sm">{{ $run['date'] }}</flux:heading>
                                    <flux:text class="text-sm text-zinc-500">{{ $run['distance_km'] }} km</flux:text>
                                </div>
                                <div class="flex gap-2">
                                    <flux:button variant="subtle" wire:click="startEditing({{ $run['id'] }})">
                                        Edit
                                    </flux:button>
                                    <flux:button
                                        variant="danger"
                                        x-on:click.prevent="if (confirm('Delete this run?')) { $wire.deleteRun({{ $run['id'] }}) }"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <flux:text>No runs logged yet.</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>