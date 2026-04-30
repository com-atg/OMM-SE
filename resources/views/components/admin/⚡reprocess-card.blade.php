<?php

use App\Jobs\ProcessSourceProjectJob;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public bool $activeOnly = true;

    public ?string $batch = null;

    public ?string $jobId = null;

    /** @var array<string, mixed>|null */
    public ?array $processSnapshot = null;

    /** @var array<int, string> */
    public array $availableBatches = [];

    public ?string $currentProjectLabel = null;

    public bool $hasActiveSource = false;

    public function mount(RedcapDestinationService $destination): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);

        try {
            $this->availableBatches = $destination->availableBatches();
        } catch (\Throwable) {
            $this->availableBatches = [];
        }

        $mapping = ProjectMapping::activeSource();
        $this->hasActiveSource = $mapping !== null;
        $this->currentProjectLabel = $mapping?->displayName();
    }

    public function startReprocess(): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);

        if ($this->jobId !== null) {
            return;
        }

        $mapping = ProjectMapping::activeSource();

        if ($mapping === null) {
            $this->addError('reprocess', 'No active source project is configured.');

            return;
        }

        $this->validate([
            'activeOnly' => ['boolean'],
            'batch' => ['nullable', 'string', Rule::in($this->availableBatches)],
        ]);

        $jobId = (string) Str::uuid();
        $pid = (string) $mapping->redcap_pid;
        $batch = ($this->batch !== null && $this->batch !== '') ? $this->batch : null;

        $initialState = ProcessSourceProjectJob::initialState($jobId, $pid, $this->activeOnly, $batch);

        Cache::put(
            ProcessSourceProjectJob::cacheKey($jobId),
            $initialState,
            now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES),
        );

        ProcessSourceProjectJob::dispatchAfterResponse(
            $jobId,
            $pid,
            $mapping->id,
            $this->activeOnly,
            $batch,
        );

        $this->jobId = $jobId;
        $this->processSnapshot = $initialState;
    }

    /**
     * Polled while the job is running. Pulls the latest cached state into a
     * component-owned property so the final result stays visible even after
     * the cache entry expires or is evicted.
     */
    public function syncState(): void
    {
        if ($this->jobId === null) {
            return;
        }

        $state = Cache::get(ProcessSourceProjectJob::cacheKey($this->jobId));

        if (is_array($state)) {
            $this->processSnapshot = $state;
        }
    }

    public function resetForm(): void
    {
        $this->jobId = null;
        $this->processSnapshot = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProcessStateProperty(): array
    {
        if ($this->jobId === null) {
            return ['status' => 'idle'];
        }

        if ($this->processSnapshot !== null) {
            return $this->processSnapshot;
        }

        $state = Cache::get(ProcessSourceProjectJob::cacheKey($this->jobId));

        return is_array($state) ? $state : ['status' => 'unknown'];
    }

    public function getProcessInProgressProperty(): bool
    {
        $status = $this->processState['status'] ?? 'idle';

        return in_array($status, ['pending', 'running'], true);
    }
}
?>

@php
    $state = $this->processState;
    $status = $state['status'] ?? 'idle';
    $isRunning = $this->processInProgress;
    $isDone = in_array($status, ['complete', 'failed'], true);
    $totalGroups = (int) ($state['total_groups'] ?? 0);
    $processedGroups = (int) ($state['processed_groups'] ?? 0);
    $progressPct = $totalGroups > 0 ? min(100, (int) round($processedGroups / $totalGroups * 100)) : 0;
    $skipReasons = is_array($state['skip_reasons'] ?? null) ? $state['skip_reasons'] : [];
    $skippedTotal = array_sum(array_map('intval', $skipReasons));
    $appliedActiveOnly = (bool) ($state['filter_active_only'] ?? false);
    $appliedBatch = $state['filter_batch'] ?? null;
@endphp

<section
    class="rounded-lg border border-white/80 bg-white/86 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur"
    @if ($jobId !== null && $isRunning) wire:poll.5s="syncState" @endif
>
    <div class="flex items-start gap-4 border-b border-slate-200/80 p-5">
        <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700">
            <flux:icon.arrow-path />
        </span>
        <div class="min-w-0 flex-1">
            <div class="text-xs font-bold uppercase tracking-[0.26em] text-slate-500">Maintenance</div>
            <h2 class="mt-1 text-xl font-bold text-slate-950">Re-process Evaluations</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Re-aggregate evaluations from the active source project and push the latest scores to the destination.
                Choose whether to limit the run to active scholars and/or a specific batch.
                @if ($hasActiveSource && $currentProjectLabel)
                    <span class="block pt-1 text-xs text-slate-400">Target: {{ $currentProjectLabel }}</span>
                @endif
            </p>
        </div>
    </div>

    <div class="p-5">
        @if (! $hasActiveSource)
            <flux:callout icon="exclamation-triangle" color="amber">
                <flux:callout.text>No active source project is configured. Configure one above before running re-processing.</flux:callout.text>
            </flux:callout>
        @elseif ($jobId === null)
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <flux:field variant="inline">
                    <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Active students only</flux:label>
                    <flux:switch wire:model.live="activeOnly" />
                </flux:field>

                <div class="hidden h-7 w-px bg-slate-200 sm:block" aria-hidden="true"></div>

                <flux:field variant="inline">
                    <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Batch</flux:label>
                    <flux:select
                        wire:model.live="batch"
                        size="sm"
                        class="min-w-40"
                        placeholder="All batches"
                    >
                        <flux:select.option value="">All batches</flux:select.option>
                        @foreach ($availableBatches as $availableBatch)
                            <flux:select.option value="{{ $availableBatch }}">{{ $availableBatch }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:button
                    wire:click="startReprocess"
                    wire:loading.attr="disabled"
                    wire:target="startReprocess"
                    variant="primary"
                    size="sm"
                    icon="arrow-path"
                    class="ml-auto"
                >
                    <span wire:loading.remove wire:target="startReprocess">Re-process now</span>
                    <span wire:loading wire:target="startReprocess">Starting…</span>
                </flux:button>
            </div>

            @error('batch')
                <p class="mt-3 text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
            @error('reprocess')
                <p class="mt-3 text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
        @else
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        @if ($isRunning)
                            <div class="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-sky-600"></div>
                            <span class="text-sm font-medium text-slate-700">
                                {{ $status === 'pending' ? 'Queued — preparing…' : 'Re-processing evaluations…' }}
                            </span>
                        @elseif ($status === 'complete')
                            <flux:icon.check-circle class="size-5 text-emerald-600" />
                            <span class="text-sm font-medium text-emerald-800">Re-processing complete</span>
                        @elseif ($status === 'failed')
                            <flux:icon.x-circle class="size-5 text-rose-600" />
                            <span class="text-sm font-medium text-rose-800">Re-processing failed</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        @if ($appliedActiveOnly)
                            <flux:badge size="sm" color="sky">Active only</flux:badge>
                        @endif
                        @if ($appliedBatch)
                            <flux:badge size="sm" color="indigo">Batch {{ $appliedBatch }}</flux:badge>
                        @endif
                        @if ($totalGroups > 0)
                            <span class="tabular-nums">{{ $processedGroups }} / {{ $totalGroups }} ({{ $progressPct }}%)</span>
                        @elseif ($isRunning)
                            <span>Fetching records…</span>
                        @endif
                    </div>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-sky-600 transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                </div>

                @if ($isDone)
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-3">
                            <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-emerald-700">Updated</div>
                            <p class="mt-1 text-2xl font-bold text-emerald-900 tabular-nums">{{ (int) ($state['updated'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-500">Unchanged</div>
                            <p class="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{{ (int) ($state['unchanged'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-rose-200 bg-rose-50/70 p-3">
                            <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-rose-700">Failed</div>
                            <p class="mt-1 text-2xl font-bold text-rose-900 tabular-nums">{{ (int) ($state['failed'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3">
                            <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-amber-700">Skipped</div>
                            <p class="mt-1 text-2xl font-bold text-amber-900 tabular-nums">{{ $skippedTotal }}</p>
                        </div>
                    </div>

                    @if ($status === 'failed' && ! empty($state['error']))
                        <pre class="mt-4 rounded-lg bg-rose-50 p-3 text-xs whitespace-pre-wrap text-rose-800">{{ $state['error'] }}</pre>
                    @endif

                    <div class="mt-4 flex justify-end">
                        <flux:button wire:click="resetForm" variant="ghost" icon="arrow-uturn-left" size="sm">Run again</flux:button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
