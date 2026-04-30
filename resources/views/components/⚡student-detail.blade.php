<?php

use App\Livewire\Dashboard;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\SemesterSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'id', except: '')]
    public string $selectedId = '';

    public bool $lockSelection = false;

    public ?string $shareableUrl = null;

    public bool $activeOnly = true;

    public ?string $selectedBatch = null;

    public function mount(?string $initialSelectedId = null, bool $lockSelection = false, ?string $shareableUrl = null): void
    {
        $this->selectedId = (string) ($initialSelectedId ?? $this->selectedId);
        $this->lockSelection = $lockSelection;
        $this->shareableUrl = $shareableUrl;

        if (! $this->lockSelection) {
            $this->activeOnly = (bool) session(Dashboard::SESSION_KEY_ACTIVE_ONLY, true);
            $batch = (string) session(Dashboard::SESSION_KEY_BATCH, '');
            $this->selectedBatch = $batch !== '' ? $batch : null;
        }
    }

    public function updatedActiveOnly(): void
    {
        session([Dashboard::SESSION_KEY_ACTIVE_ONLY => $this->activeOnly]);
        $this->selectedId = '';
    }

    public function updatedSelectedBatch(): void
    {
        session([Dashboard::SESSION_KEY_BATCH => $this->selectedBatch ?? '']);
        $this->selectedId = '';
    }

    public function render(): View
    {
        $destination = app(RedcapDestinationService::class);
        $records = $destination->getAllStudentRecords();
        $availableBatches = $this->lockSelection ? [] : $destination->availableBatches();

        if ($this->selectedBatch !== null && ! in_array($this->selectedBatch, $availableBatches, true)) {
            $this->selectedBatch = null;
            session([Dashboard::SESSION_KEY_BATCH => '']);
        }

        $filteredRecords = $this->lockSelection ? $records : $this->filterRecords($records);

        $selectedRecord = $this->resolveRecord($filteredRecords, $this->selectedId);
        $selected = $selectedRecord ? $this->selectedStudent($selectedRecord) : null;
        $semesters = $selectedRecord ? $this->buildSemesters($selectedRecord) : [];

        return view('components.⚡student-detail', [
            'roster' => $this->lockSelection ? [] : $this->roster($filteredRecords),
            'selected' => $selected,
            'semesters' => $semesters,
            'availableBatches' => $availableBatches,
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<int,array<string,mixed>>
     */
    private function filterRecords(array $records): array
    {
        return collect($records)
            ->filter(function (array $record): bool {
                if ($this->activeOnly && (string) ($record['is_active'] ?? '') !== '1') {
                    return false;
                }

                if ($this->selectedBatch !== null && trim((string) ($record['batch'] ?? '')) !== $this->selectedBatch) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<int,array{record_id:string,name:string}>
     */
    private function roster(array $records): array
    {
        return collect($records)
            ->map(fn (array $record): array => [
                'record_id' => (string) ($record['record_id'] ?? ''),
                'name' => $this->displayName($record),
            ])
            ->filter(fn (array $record): bool => $record['record_id'] !== '' && $record['name'] !== '')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<string,mixed>|null
     */
    private function resolveRecord(array $records, string $recordId): ?array
    {
        if ($recordId === '') {
            return null;
        }

        return collect($records)->firstWhere('record_id', $recordId) ?: null;
    }

    /**
     * @param  array<string,mixed>  $record
     */
    private function displayName(array $record): string
    {
        $first = trim((string) ($record['goes_by'] ?? '')) ?: trim((string) ($record['first_name'] ?? ''));
        $last = trim((string) ($record['last_name'] ?? ''));

        return trim($first.' '.$last);
    }

    /**
     * @param  array<string,mixed>  $record
     * @return array{record_id:string,name:string,datatelid:string|null,photo_url:string|null}
     */
    private function selectedStudent(array $record): array
    {
        $datatelId = trim((string) ($record['datatelid'] ?? ''));

        return [
            'record_id' => (string) $record['record_id'],
            'name' => $this->displayName($record),
            'datatelid' => $datatelId !== '' ? $datatelId : null,
            'photo_url' => $datatelId !== '' ? 'https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id='.$datatelId : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $record
     * @return array<int,array<string,mixed>>
     */
    private function buildSemesters(array $record): array
    {
        $categories = RedcapSourceService::DEST_CATEGORY;
        $labels = array_values(RedcapSourceService::CATEGORY_LABELS);
        $categoryKeys = array_values($categories);

        $cohortTerm = trim((string) ($record['cohort_start_term'] ?? '')) ?: null;
        $cohortYearRaw = trim((string) ($record['cohort_start_year'] ?? ''));
        $cohortYear = $cohortYearRaw !== '' && ctype_digit($cohortYearRaw) ? (int) $cohortYearRaw : null;
        $slotLabels = SemesterSlot::labelsFor($cohortTerm, $cohortYear);

        $out = [];

        foreach (SemesterSlot::slotKeys() as $slotIdx => $slotKey) {
            $counts = [];
            $averages = [];
            $dates = [];
            $total = 0;

            foreach ($categoryKeys as $catKey) {
                $nu = (int) ($record["{$slotKey}_nu_{$catKey}"] ?? 0);
                $avgRaw = $record["{$slotKey}_avg_{$catKey}"] ?? '';
                $datesRaw = trim((string) ($record["{$slotKey}_dates_{$catKey}"] ?? ''));

                $counts[] = $nu;
                $averages[] = ($avgRaw !== '' && is_numeric($avgRaw)) ? (float) $avgRaw : null;
                $dates[$catKey] = $datesRaw !== '' ? array_map('trim', explode(';', $datesRaw)) : [];
                $total += $nu;
            }

            $out[] = [
                'slug' => $slotKey,
                'label' => $slotLabels[$slotIdx] ?? "Semester {$slotIdx}",
                'category_labels' => $labels,
                'category_keys' => $categoryKeys,
                'counts' => $counts,
                'averages' => $averages,
                'dates' => $dates,
                'total' => $total,
                'final_score' => ($record["{$slotKey}_final_score"] ?? '') !== '' ? (float) $record["{$slotKey}_final_score"] : null,
                'leadership' => ($record["{$slotKey}_leadership"] ?? '') !== '' ? (int) $record["{$slotKey}_leadership"] : null,
                'comments_count' => (int) ($record["{$slotKey}_nu_comments"] ?? 0),
                'comments' => $this->parseComments(trim((string) ($record["{$slotKey}_comments"] ?? ''))),
                'monthly' => $this->buildMonthly($dates, $categoryKeys),
            ];
        }

        return $out;
    }

    /**
     * @return array<int,array{faculty:string,date:string,category:string,comment:string}>
     */
    private function parseComments(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $rows = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(';', $line, 4));
            $rows[] = [
                'faculty' => $parts[0] ?? '',
                'date' => $parts[1] ?? '',
                'category' => $parts[2] ?? '',
                'comment' => $parts[3] ?? $line,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string,list<string>>  $dates
     * @param  list<string>  $categoryKeys
     * @return array<string,array<string,int>>
     */
    private function buildMonthly(array $dates, array $categoryKeys): array
    {
        $buckets = [];

        foreach ($categoryKeys as $catKey) {
            foreach ($dates[$catKey] ?? [] as $entry) {
                $datePart = trim((string) strrchr($entry, ','), ', ');

                if ($datePart === '') {
                    continue;
                }

                try {
                    $monthKey = Carbon::parse($datePart)->format('M Y');
                } catch (Throwable) {
                    continue;
                }

                $buckets[$monthKey] ??= [];
                $buckets[$monthKey][$catKey] = ($buckets[$monthKey][$catKey] ?? 0) + 1;
            }
        }

        uksort($buckets, fn (string $a, string $b): int => Carbon::parse("1 {$a}")->timestamp <=> Carbon::parse("1 {$b}")->timestamp);

        return $buckets;
    }

};

$categoryKeys = $semesters[0]['category_keys'] ?? [];
$categoryLabels = $semesters[0]['category_labels'] ?? [];
$monthKeys = [];
$mergedMonthly = [];

foreach ($semesters as $sem) {
    foreach (array_keys($sem['monthly']) as $month) {
        $monthKeys[] = $month;
    }

    foreach ($sem['monthly'] as $month => $cats) {
        foreach ($cats as $catKey => $count) {
            $mergedMonthly[$month][$catKey] = ($mergedMonthly[$month][$catKey] ?? 0) + $count;
        }
    }
}

$monthKeys = array_values(array_unique($monthKeys));
$totalEvaluations = collect($semesters)->sum('total');
$totalComments = collect($semesters)->sum('comments_count');
$leadershipRows = collect($semesters)
    ->map(fn (array $sem): array => [
        'label' => $sem['label'],
        'points' => $sem['leadership'],
        'max' => 10,
    ])
    ->values();
$leadershipEarned = $leadershipRows->sum(fn (array $row): int => (int) ($row['points'] ?? 0));
$leadershipMax = $leadershipRows->sum('max');
$hasLeadershipPoints = $leadershipRows->contains(fn (array $row): bool => $row['points'] !== null);
$allComments = collect($semesters)
    ->flatMap(fn (array $sem): array => collect($sem['comments'])
        ->map(fn (array $comment): array => $comment + ['semester' => $sem['label']])
        ->all())
    ->values();
$selectedInitials = collect(explode(' ', $selected['name'] ?? ''))
    ->filter()
    ->map(fn (string $part) => mb_substr($part, 0, 1))
    ->take(2)
    ->implode('');
$chartPayload = [
    'semesters' => $semesters,
    'categoryLabels' => $categoryLabels,
    'categoryKeys' => $categoryKeys,
    'mergedMonthly' => $mergedMonthly,
    'monthKeys' => $monthKeys,
];
?>

<div class="flex flex-col gap-7">
    @unless ($lockSelection)
        <section class="overflow-hidden rounded-xl border border-white/80 bg-white/95 shadow-[0_8px_24px_rgba(15,23,42,0.05)] backdrop-blur">
            <div class="flex flex-col divide-y divide-slate-200/70 md:flex-row md:items-stretch md:divide-x md:divide-y-0">
                <div class="relative flex items-center gap-3 bg-gradient-to-br from-sky-50 via-white to-slate-50 px-5 py-4 md:min-w-[200px]">
                    <span class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-sky-400 to-indigo-500"></span>
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-sky-600 shadow-sm ring-1 ring-sky-100">
                        <flux:icon.funnel variant="mini" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-[0.65rem] font-semibold uppercase tracking-[0.22em] text-sky-700">Filters</div>
                        <div class="truncate text-sm font-semibold text-slate-900">Student scope</div>
                    </div>
                </div>

                <div class="flex flex-1 flex-wrap items-center gap-x-5 gap-y-3 px-5 py-4">
                    <flux:field variant="inline">
                        <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Student</flux:label>
                        <flux:select
                            wire:model.live="selectedId"
                            size="sm"
                            class="min-w-56"
                            placeholder="Select a student..."
                        >
                            <flux:select.option value="">Select a student...</flux:select.option>
                            @foreach ($roster as $student)
                                <flux:select.option value="{{ $student['record_id'] }}" wire:key="student-option-{{ $student['record_id'] }}">
                                    {{ $student['name'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <div class="hidden h-7 w-px bg-slate-200 md:block" aria-hidden="true"></div>

                    <flux:field variant="inline">
                        <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Active only</flux:label>
                        <flux:switch wire:model.live="activeOnly" />
                    </flux:field>

                    <div class="hidden h-7 w-px bg-slate-200 md:block" aria-hidden="true"></div>

                    <flux:field variant="inline">
                        <flux:label class="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-slate-500">Batch</flux:label>
                        <flux:select
                            wire:model.live="selectedBatch"
                            size="sm"
                            class="min-w-40"
                            placeholder="All batches"
                        >
                            <flux:select.option value="">All batches</flux:select.option>
                            @foreach ($availableBatches as $batch)
                                <flux:select.option value="{{ $batch }}">{{ $batch }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
            </div>
        </section>
    @endunless

    @if (! $selected)
        <section class="rounded-lg border border-[#d8e3fa] bg-white/90 p-10 text-center shadow-[0_14px_38px_rgba(26,54,93,0.05)]">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-[#e7eeff] text-[#455f88]">
                <flux:icon.user-group variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-[#111c2c]">Select a student</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-[#43474e]">
                Use the student selector to open an individual evaluation profile.
            </p>
        </section>
    @else
        <section class="flex flex-col gap-6" wire:key="student-detail-{{ $selected['record_id'] }}">
            <div class="rounded-lg border border-[#d8e3fa] bg-white/92 p-5 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-5">
                        <div class="shrink-0 overflow-hidden rounded-lg border border-[#cfdaf1] bg-[#e7eeff]">
                            @if ($selected['photo_url'])
                                <img
                                    src="{{ $selected['photo_url'] }}"
                                    alt="{{ $selected['name'] }}"
                                    class="size-28 object-cover"
                                    onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('grid');"
                                >
                                <div class="hidden size-28 place-items-center bg-[#002045] text-3xl font-bold text-white">
                                    {{ $selectedInitials }}
                                </div>
                            @else
                                <div class="grid size-28 place-items-center bg-[#002045] text-3xl font-bold text-white">
                                    {{ $selectedInitials }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-2xl font-semibold tracking-tight text-[#111c2c] sm:text-3xl">{{ $selected['name'] }}</h2>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 sm:flex sm:flex-row sm:flex-wrap lg:flex-none lg:flex-nowrap">
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3 text-center sm:w-28">
                            <dt class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Evals</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($totalEvaluations) }}</dd>
                        </div>
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3 text-center sm:w-28">
                            <dt class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Comments</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($totalComments) }}</dd>
                        </div>
                        <div class="col-span-2 rounded-lg border border-[#e2e8f0] bg-[#f4fbfa] p-3 text-center sm:col-auto sm:w-44">
                            <dt class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-[#006a63]">Leadership</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-[#111c2c]">
                                {{ $hasLeadershipPoints ? number_format($leadershipEarned).'/'.number_format($leadershipMax) : 'Pending' }}
                            </dd>
                            <div class="mt-2 flex flex-wrap justify-center gap-1 text-[0.6rem] font-semibold uppercase tracking-[0.06em] text-[#52606d]">
                                @foreach ($leadershipRows as $row)
                                    <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-[#dce1e8]">{{ $row['label'] }} {{ $row['points'] ?? '–' }}</span>
                                @endforeach
                            </div>
                        </div>
                    </dl>
                </div>

                @if (! empty($shareableUrl))
                    <div class="mt-5 rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-3">
                        <div class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Shareable Link</div>
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <code class="min-w-0 flex-1 truncate rounded-md bg-white px-2.5 py-2 text-xs text-[#43474e] ring-1 ring-[#d8e3fa]">{{ $shareableUrl }}</code>
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="clipboard"
                                onclick="copyStudentLink(this, @js($shareableUrl))"
                            >
                                Copy link
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="flex min-w-0 flex-col gap-6">
                    @if (count($monthKeys) > 0)
                        <section class="rounded-lg border border-[#d8e3fa] bg-white/92 p-6 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[#455f88]">Activity</div>
                                    <h2 class="mt-2 text-lg font-semibold text-[#111c2c]">Monthly Evaluation Volume</h2>
                                </div>
                                <span class="rounded-full bg-[#d6e3ff] px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-[#001b3c]">{{ count($monthKeys) }} months</span>
                            </div>
                            <div class="mt-6 h-72">
                                <canvas data-student-chart="monthly"></canvas>
                            </div>
                        </section>
                    @endif

                    @foreach ($semesters as $i => $sem)
                        @if ($sem['total'] === 0)
                            @continue
                        @endif
                        <section class="rounded-lg border border-[#d8e3fa] bg-white/92 p-6 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur" wire:key="semester-{{ $selected['record_id'] }}-{{ $sem['slug'] }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[#455f88]">{{ $sem['label'] }} Semester</div>
                                    <h2 class="mt-2 text-lg font-semibold text-[#111c2c]">Evaluation Summary</h2>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($sem['leadership'] !== null)
                                        <flux:badge color="teal">Leadership {{ $sem['leadership'] }}/10</flux:badge>
                                    @endif
                                    <flux:badge color="zinc">{{ number_format($sem['total']) }} evals</flux:badge>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                                <div class="overflow-hidden rounded-lg border border-[#e2e8f0] bg-white">
                                    <flux:table>
                                        <flux:table.columns>
                                            <flux:table.column class="bg-[#f0f3ff] px-6 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Category</flux:table.column>
                                            <flux:table.column class="bg-[#f0f3ff] px-6 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Evals</flux:table.column>
                                            <flux:table.column class="bg-[#f0f3ff] px-6 text-xs font-bold uppercase tracking-[0.16em] text-[#455f88]" align="center">Avg</flux:table.column>
                                        </flux:table.columns>
                                        <flux:table.rows>
                                            @foreach ($sem['category_keys'] as $j => $catKey)
                                                <flux:table.row class="transition hover:bg-[#f9f9ff]" wire:key="semester-{{ $sem['slug'] }}-{{ $catKey }}">
                                                    <flux:table.cell class="px-6 font-semibold text-[#111c2c]" align="center">{{ $sem['category_labels'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="px-6 font-medium tabular-nums text-[#43474e]" align="center">{{ $sem['counts'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="px-6 tabular-nums" align="center">
                                                        @if ($sem['averages'][$j] !== null)
                                                            <span class="font-semibold text-[#111c2c]">{{ number_format($sem['averages'][$j], 1) }}</span>
                                                            <span class="text-xs text-[#74777f]">/100</span>
                                                        @else
                                                            <span class="text-[#74777f]">-</span>
                                                        @endif
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>

                                <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4">
                                    <div class="mb-3 text-sm font-semibold text-[#111c2c]">Evaluations by Category</div>
                                    <div class="h-56">
                                        <canvas data-student-chart="semester" data-semester-index="{{ $i }}"></canvas>
                                    </div>
                                </div>
                            </div>

                            @php $hasDates = collect($sem['dates'])->some(fn ($entries) => count($entries) > 0); @endphp
                            @if ($hasDates)
                                <div class="mt-5 rounded-lg border border-[#e2e8f0] bg-white p-5">
                                    <div class="mb-4 flex items-center gap-2 text-[0.72rem] font-bold uppercase tracking-[0.22em] text-[#455f88]">
                                        <flux:icon.calendar-days variant="mini" class="size-4" />
                                        Evaluation Dates by Category
                                    </div>
                                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($sem['category_keys'] as $j => $catKey)
                                            @if (! empty($sem['dates'][$catKey]))
                                                <div class="min-w-0 border-l border-[#d8e3fa] pl-4">
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <span class="size-2 rounded-full bg-[#006a63]"></span>
                                                        <p class="text-sm font-semibold text-[#111c2c]">{{ $sem['category_labels'][$j] }}</p>
                                                    </div>
                                                    <ul class="space-y-1.5">
                                                        @foreach ($sem['dates'][$catKey] as $entry)
                                                            <li class="text-sm leading-5 text-[#43474e]">{{ $entry }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>

                <aside class="rounded-lg border border-[#d8e3fa] bg-white/92 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur xl:sticky xl:top-24 xl:self-start">
                    <div class="flex items-center justify-between border-b border-[#e2e8f0] p-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 place-items-center rounded-lg bg-[#d6e3ff] text-[#002045]">
                                <flux:icon.chat-bubble-left-right variant="mini" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-[#111c2c]">Faculty Comments</h2>
                                <p class="text-xs text-[#74777f]">{{ number_format($allComments->count()) }} total</p>
                            </div>
                        </div>
                    </div>

                    @if ($allComments->isEmpty())
                        <div class="p-6 text-sm leading-6 text-[#74777f]">
                            No faculty comments have been recorded for this student.
                        </div>
                    @else
                        <div class="max-h-[720px] space-y-5 overflow-y-auto p-5">
                            @foreach ($allComments as $comment)
                                <article class="border-l border-[#cfdaf1] pl-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="font-semibold text-[#111c2c]">{{ $comment['faculty'] ?: 'Faculty' }}</div>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#74777f]">
                                                <span>{{ $comment['semester'] }}</span>
                                                @if ($comment['category'] !== '')
                                                    <span class="rounded-full bg-[#e7eeff] px-2 py-0.5 text-[#455f88]">{{ $comment['category'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($comment['date'] !== '')
                                            <span class="shrink-0 text-xs text-[#98a2b3]">{{ $comment['date'] }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-3 text-sm italic leading-6 text-[#263142]">"{{ $comment['comment'] }}"</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </aside>
            </div>
        </section>
    @endif

    <script type="application/json" data-student-chart-payload>@json($chartPayload)</script>
</div>
