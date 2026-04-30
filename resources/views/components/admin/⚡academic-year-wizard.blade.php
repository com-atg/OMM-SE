<?php

use App\Jobs\ImportScholarsJob;
use App\Models\ProjectMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $savedProjectMappingId = null;

    public ?string $savedSummary = null;

    public string $redcap_pid = '';

    public string $redcap_token = '';

    public bool $importExpanded = false;

    public ?string $importJobId = null;

    public function saveProjectMapping(): void
    {
        $validated = $this->validate([
            'redcap_pid' => [
                'required',
                'integer',
                'between:1,4294967295',
                Rule::unique('project_mappings', 'redcap_pid')->whereNull('deleted_at'),
            ],
            'redcap_token' => ['required', 'string', 'max:255'],
        ]);

        $mapping = DB::transaction(function () use ($validated): ProjectMapping {
            ProjectMapping::query()->where('is_active', true)->update(['is_active' => false]);

            return ProjectMapping::create($validated + ['is_active' => true]);
        });

        $this->savedProjectMappingId = $mapping->id;
        $this->savedSummary = $mapping->displayName();
    }

    public function startImport(): void
    {
        if ($this->savedProjectMappingId === null || $this->importJobId !== null) {
            return;
        }

        $jobId = (string) Str::uuid();

        Cache::put(
            ImportScholarsJob::cacheKey($jobId),
            ImportScholarsJob::initialState($jobId, $this->savedProjectMappingId),
            now()->addMinutes(ImportScholarsJob::TTL_MINUTES),
        );

        ImportScholarsJob::dispatchAfterResponse($jobId, $this->savedProjectMappingId);

        $this->importJobId = $jobId;
        $this->importExpanded = true;
    }

    public function toggleImportPanel(): void
    {
        $this->importExpanded = ! $this->importExpanded;
    }

    /**
     * @return array<string, mixed>
     */
    public function getImportStateProperty(): array
    {
        if ($this->importJobId === null) {
            return ['status' => 'idle'];
        }

        $state = Cache::get(ImportScholarsJob::cacheKey($this->importJobId));

        return is_array($state) ? $state : ['status' => 'unknown'];
    }

    public function getImportInProgressProperty(): bool
    {
        $status = $this->importState['status'] ?? 'idle';

        return in_array($status, ['pending', 'running'], true);
    }
}
?>

<div class="space-y-5">
    {{-- Step 5: Project Mapping --}}
    <div
        @class([
            'rounded-lg p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)]',
            'border border-emerald-200' => $savedProjectMappingId === null,
            'border border-emerald-300' => $savedProjectMappingId !== null,
        ])
        style="background-color: #ecfdf5;"
    >
        <div class="flex items-start gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-700">
                @if ($savedProjectMappingId !== null)
                    <flux:icon.check-circle class="size-6 text-emerald-600" />
                @else
                    <span class="text-sm font-bold">5</span>
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-bold uppercase tracking-[0.26em] text-emerald-700">Step 5</div>
                <h2 class="mt-2 text-xl font-bold text-emerald-900">Save the new project mapping</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-800">
                    Once the four REDCap-side steps above are complete, enter the new project's details below.
                </p>
            </div>
        </div>

        @if ($savedProjectMappingId === null)
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="redcap_pid" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">REDCap PID</label>
                    <input
                        type="text"
                        wire:model="redcap_pid"
                        id="redcap_pid"
                        placeholder="1846"
                        inputmode="numeric"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('redcap_pid') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                    >
                    @error('redcap_pid')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="redcap_token" class="mb-1 block text-xs font-bold uppercase tracking-[0.22em] text-slate-500">REDCap Token</label>
                    <input
                        type="password"
                        wire:model="redcap_token"
                        id="redcap_token"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100 @error('redcap_token') border-red-400 focus:border-red-400 focus:ring-red-100 @enderror"
                    >
                    @error('redcap_token')
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2 sm:flex sm:justify-end">
                    <flux:button wire:click="saveProjectMapping" wire:loading.attr="disabled" wire:target="saveProjectMapping" variant="primary" icon="check">
                        <span wire:loading.remove wire:target="saveProjectMapping">Save project mapping</span>
                        <span wire:loading wire:target="saveProjectMapping">Saving...</span>
                    </flux:button>
                </div>
            </div>
        @else
            <div class="mt-5 rounded-lg border border-emerald-200 bg-white/70 px-4 py-3 text-sm text-emerald-900">
                <span class="font-semibold">Saved:</span> {{ $savedSummary }}
            </div>
        @endif
    </div>

    {{-- Step 6: Import Scholars --}}
    @php
        $step7Locked = $savedProjectMappingId === null;
        $importState = $this->importState;
        $importStatus = $importState['status'] ?? 'idle';
        $importDone = in_array($importStatus, ['complete', 'failed'], true);
        $totalFetched = (int) ($importState['total_fetched'] ?? 0);
        $processed = (int) ($importState['processed'] ?? 0);
        $createdRows = $importState['created'] ?? [];
        $updatedRows = $importState['updated'] ?? [];
        $missingRows = $importState['missing_email'] ?? [];
        $failedRows = $importState['failed'] ?? [];
        $createdCount = count($createdRows);
        $updatedCount = count($updatedRows);
        $missingCount = count($missingRows);
        $failedCount = count($failedRows);
        $progressPct = $totalFetched > 0 ? min(100, (int) round($processed / $totalFetched * 100)) : 0;
    @endphp
    <div
        @class([
            'rounded-lg p-6 shadow-[0_18px_50px_rgba(15,23,42,0.07)]',
            'border border-slate-200 bg-slate-50/60' => $step7Locked,
            'border border-emerald-200' => ! $step7Locked,
        ])
        @style([
            'background-color: #ecfdf5;' => ! $step7Locked,
        ])
        @if ($importJobId !== null && $this->importInProgress)
            wire:poll.5s
        @endif
    >
        <div class="flex items-start gap-4">
            <span @class([
                'grid size-11 shrink-0 place-items-center rounded-lg text-sm font-bold',
                'bg-slate-200 text-slate-500' => $step7Locked,
                'bg-emerald-100 text-emerald-700' => ! $step7Locked && $importStatus !== 'complete',
                'bg-emerald-100' => $importStatus === 'complete',
            ])>
                @if ($importStatus === 'complete')
                    <flux:icon.check-circle class="size-6 text-emerald-600" />
                @else
                    6
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div @class([
                            'text-xs font-bold uppercase tracking-[0.26em]',
                            'text-slate-500' => $step7Locked,
                            'text-emerald-700' => ! $step7Locked,
                        ])>Step 6</div>
                        <h2 @class([
                            'mt-2 text-xl font-bold',
                            'text-slate-600' => $step7Locked,
                            'text-emerald-900' => ! $step7Locked,
                        ])>Import scholars from OMM ACE List</h2>
                        <p @class([
                            'mt-2 text-sm leading-6',
                            'text-slate-500' => $step7Locked,
                            'text-emerald-800' => ! $step7Locked,
                        ])>
                            @if ($step7Locked)
                                Save the project mapping above to unlock scholar import.
                            @else
                                Pull the entire roster from the OMM ACE List, create student-role users for any new entries,
                                and refresh existing users with their latest batch and is_active values.
                            @endif
                        </p>
                    </div>

                    @if (! $step7Locked && $importJobId === null)
                        <flux:button wire:click="toggleImportPanel" variant="ghost" :icon="$importExpanded ? 'chevron-up' : 'chevron-down'">
                            {{ $importExpanded ? 'Collapse' : 'Import students' }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        @if (! $step7Locked && ($importExpanded || $importJobId !== null))
            <div class="mt-6 space-y-4">
                @if ($importJobId === null)
                    <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-white/70 p-4">
                        <p class="text-sm text-emerald-900">
                            Ready to import scholars from REDCap. This may take a moment depending on roster size.
                        </p>
                        <flux:button wire:click="startImport" variant="primary" icon="arrow-down-tray">Start import</flux:button>
                    </div>
                @else
                    <div class="rounded-lg border border-emerald-200 bg-white/70 p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                @if ($this->importInProgress)
                                    <div class="size-4 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600"></div>
                                    <span class="text-sm font-medium text-slate-700">
                                        {{ $importStatus === 'pending' ? 'Queued — preparing...' : 'Importing scholars...' }}
                                    </span>
                                @elseif ($importStatus === 'complete')
                                    <flux:icon.check-circle class="size-5 text-emerald-600" />
                                    <span class="text-sm font-medium text-emerald-800">Import complete</span>
                                @elseif ($importStatus === 'failed')
                                    <flux:icon.x-circle class="size-5 text-rose-600" />
                                    <span class="text-sm font-medium text-rose-800">Import failed</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500">
                                @if ($totalFetched > 0)
                                    {{ $processed }} / {{ $totalFetched }} ({{ $progressPct }}%)
                                @elseif ($this->importInProgress)
                                    Fetching records...
                                @endif
                            </div>
                        </div>

                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-emerald-600 transition-all duration-300" style="width: {{ $progressPct }}%"></div>
                        </div>

                        @if ($importDone)
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-slate-500">Fetched</div>
                                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalFetched }}</p>
                                </div>
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/80 p-3">
                                    <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-emerald-700">Created</div>
                                    <p class="mt-1 text-2xl font-bold text-emerald-900">{{ $createdCount }}</p>
                                </div>
                                <div class="rounded-lg border border-sky-200 bg-sky-50 p-3">
                                    <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-sky-700">Updated</div>
                                    <p class="mt-1 text-2xl font-bold text-sky-900">{{ $updatedCount }}</p>
                                </div>
                                <div class="rounded-lg border border-amber-200 bg-amber-50/80 p-3">
                                    <div class="text-[0.65rem] font-bold uppercase tracking-[0.2em] text-amber-700">Missing email</div>
                                    <p class="mt-1 text-2xl font-bold text-amber-900">{{ $missingCount }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($importStatus === 'failed' && ! empty($importState['error']))
                            <pre class="mt-4 rounded-lg bg-rose-50 p-3 text-xs whitespace-pre-wrap text-rose-800">{{ $importState['error'] }}</pre>
                        @endif
                    </div>

                    @if ($importDone && $createdCount > 0)
                        <div class="overflow-hidden rounded-lg border border-emerald-200 bg-white/70">
                            <div class="border-b border-emerald-200 px-4 py-3">
                                <div class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">Newly created users ({{ $createdCount }})</div>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-emerald-50/80 text-[0.7rem] uppercase tracking-[0.22em] text-emerald-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Name</th>
                                            <th class="px-4 py-2 text-left">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100">
                                        @foreach ($createdRows as $row)
                                            <tr>
                                                <td class="px-4 py-2 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                                <td class="px-4 py-2 text-slate-600">{{ $row['email'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($importDone && $missingCount > 0)
                        <div class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50/40">
                            <div class="border-b border-amber-200 px-4 py-3">
                                <div class="text-xs font-bold uppercase tracking-[0.22em] text-amber-700">Records missing email ({{ $missingCount }})</div>
                                <p class="mt-1 text-xs text-amber-800">Update these in REDCap, then re-run the import from the settings page.</p>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-amber-100/40 text-[0.7rem] uppercase tracking-[0.22em] text-amber-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left">REDCap record ID</th>
                                            <th class="px-4 py-2 text-left">Name</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-amber-100">
                                        @foreach ($missingRows as $row)
                                            <tr>
                                                <td class="px-4 py-2 font-mono text-xs text-amber-900">{{ $row['record_id'] }}</td>
                                                <td class="px-4 py-2 text-amber-800">{{ $row['name'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if ($importDone && $failedCount > 0)
                        <div class="overflow-hidden rounded-lg border border-rose-200 bg-rose-50/40">
                            <div class="border-b border-rose-200 px-4 py-3">
                                <div class="text-xs font-bold uppercase tracking-[0.22em] text-rose-700">Records that failed ({{ $failedCount }})</div>
                                <p class="mt-1 text-xs text-rose-800">These records hit an error and were not imported.</p>
                            </div>
                            <div class="max-h-72 overflow-y-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-rose-100/40 text-[0.7rem] uppercase tracking-[0.22em] text-rose-700">
                                        <tr>
                                            <th class="px-4 py-2 text-left">REDCap record ID</th>
                                            <th class="px-4 py-2 text-left">Name</th>
                                            <th class="px-4 py-2 text-left">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-rose-100">
                                        @foreach ($failedRows as $row)
                                            <tr>
                                                <td class="px-4 py-2 font-mono text-xs text-rose-900">{{ $row['record_id'] }}</td>
                                                <td class="px-4 py-2 text-rose-800">{{ $row['name'] }}</td>
                                                <td class="px-4 py-2 text-rose-700">{{ $row['reason'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </div>
</div>
