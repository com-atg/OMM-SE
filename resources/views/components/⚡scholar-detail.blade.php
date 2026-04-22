<?php

use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
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

    private const SEMESTERS = ['spring' => 'Spring', 'fall' => 'Fall'];

    public function mount(?string $initialSelectedId = null, bool $lockSelection = false, ?string $shareableUrl = null): void
    {
        $this->selectedId = (string) ($initialSelectedId ?? $this->selectedId);
        $this->lockSelection = $lockSelection;
        $this->shareableUrl = $shareableUrl;
    }

    public function render(): View
    {
        $records = app(RedcapDestinationService::class)->getAllScholarRecords();
        $selectedRecord = $this->resolveRecord($records, $this->selectedId);
        $selected = $selectedRecord ? $this->selectedScholar($selectedRecord) : null;
        $semesters = $selectedRecord ? $this->buildSemesters($selectedRecord) : [];

        return view('components.⚡scholar-detail', [
            'roster' => $this->lockSelection ? [] : $this->roster($records),
            'selected' => $selected,
            'semesters' => $semesters,
            'finalGrade' => $this->finalGrade($semesters),
        ]);
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
    private function selectedScholar(array $record): array
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
        $out = [];

        foreach (self::SEMESTERS as $slug => $label) {
            $counts = [];
            $averages = [];
            $dates = [];
            $total = 0;

            foreach ($categoryKeys as $catKey) {
                $nu = (int) ($record["{$slug}_nu_{$catKey}"] ?? 0);
                $avgRaw = $record["{$slug}_avg_{$catKey}"] ?? '';
                $datesRaw = trim((string) ($record["{$slug}_dates_{$catKey}"] ?? ''));

                $counts[] = $nu;
                $averages[] = ($avgRaw !== '' && is_numeric($avgRaw)) ? (float) $avgRaw : null;
                $dates[$catKey] = $datesRaw !== '' ? array_map('trim', explode(';', $datesRaw)) : [];
                $total += $nu;
            }

            $out[] = [
                'slug' => $slug,
                'label' => $label,
                'category_labels' => $labels,
                'category_keys' => $categoryKeys,
                'counts' => $counts,
                'averages' => $averages,
                'dates' => $dates,
                'total' => $total,
                'final_score' => ($record["{$slug}_final_score"] ?? '') !== '' ? (float) $record["{$slug}_final_score"] : null,
                'leadership' => ($record["{$slug}_leadership"] ?? '') !== '' ? (int) $record["{$slug}_leadership"] : null,
                'comments_count' => (int) ($record["{$slug}_nu_comments"] ?? 0),
                'comments' => $this->parseComments(trim((string) ($record["{$slug}_comments"] ?? ''))),
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

    /**
     * @param  array<int,array<string,mixed>>  $semesters
     * @return array{label:string,score:float}|null
     */
    private function finalGrade(array $semesters): ?array
    {
        $semester = collect($semesters)
            ->reverse()
            ->first(fn (array $semester): bool => $semester['final_score'] !== null);

        if (! $semester) {
            return null;
        }

        return [
            'label' => (string) $semester['label'],
            'score' => (float) $semester['final_score'],
        ];
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
        <section class="rounded-lg border border-white/80 bg-white/84 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
            <flux:select
                class="max-w-sm"
                wire:model.live="selectedId"
                label="Choose a scholar"
            >
                <flux:select.option value="">Select a scholar...</flux:select.option>
                @foreach ($roster as $scholar)
                    <flux:select.option value="{{ $scholar['record_id'] }}" wire:key="scholar-option-{{ $scholar['record_id'] }}">
                        {{ $scholar['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </section>
    @endunless

    @if (! $selected)
        <section class="rounded-lg border border-slate-200 bg-white/84 p-10 text-center shadow-sm">
            <div class="mx-auto grid size-12 place-items-center rounded-lg bg-slate-100 text-slate-500">
                <flux:icon.user-group variant="mini" />
            </div>
            <h2 class="mt-4 text-lg font-semibold text-slate-950">Select a scholar</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                Use the scholar selector to open an individual evaluation profile.
            </p>
        </section>
    @else
        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[320px_1fr]" wire:key="scholar-detail-{{ $selected['record_id'] }}">
            <aside class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                    @if ($selected['photo_url'])
                        <img
                            src="{{ $selected['photo_url'] }}"
                            alt="{{ $selected['name'] }}"
                            class="aspect-[4/5] w-full object-cover"
                            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('grid');"
                        >
                        <div class="hidden aspect-[4/5] w-full place-items-center bg-slate-900 text-5xl font-bold text-white">
                            {{ $selectedInitials }}
                        </div>
                    @else
                        <div class="grid aspect-[4/5] w-full place-items-center bg-slate-900 text-5xl font-bold text-white">
                            {{ $selectedInitials }}
                        </div>
                    @endif
                </div>

                <div class="mt-5">
                    <div class="text-[0.7rem] font-bold uppercase tracking-[0.32em] text-sky-700">Scholar Profile</div>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $selected['name'] }}</h2>

                    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-[0.68rem] font-bold uppercase tracking-[0.24em] text-amber-700">Final Grade</div>
                                @if ($finalGrade)
                                    <div class="mt-2 text-4xl font-extrabold tracking-tight text-amber-950 tabular-nums">
                                        {{ number_format($finalGrade['score'], 2) }}
                                    </div>
                                    <p class="mt-1 text-sm font-medium text-amber-800">{{ $finalGrade['label'] }} semester</p>
                                @else
                                    <div class="mt-2 text-2xl font-bold tracking-tight text-amber-950">Pending</div>
                                    <p class="mt-1 text-sm font-medium text-amber-800">Final grade is not available yet.</p>
                                @endif
                            </div>
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700">
                                <flux:icon.academic-cap variant="mini" />
                            </span>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Record</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ $selected['record_id'] }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Datatel</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ $selected['datatelid'] ?? '-' }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Evals</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ number_format($totalEvaluations) }}</dd>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white/78 p-3">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Comments</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-950">{{ number_format($totalComments) }}</dd>
                        </div>
                    </dl>

                    @if (! empty($shareableUrl))
                        <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="text-[0.68rem] font-bold uppercase tracking-[0.22em] text-slate-500">Shareable Link</div>
                            <code class="mt-2 block truncate rounded-md bg-white px-2.5 py-2 text-xs text-slate-600 ring-1 ring-slate-200">{{ $shareableUrl }}</code>
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="clipboard"
                                class="mt-2 w-full"
                                onclick="copyScholarLink(this, @js($shareableUrl))"
                            >
                                Copy link
                            </flux:button>
                        </div>
                    @endif
                </div>
            </aside>

            <div class="flex min-w-0 flex-col gap-5">
                @if (count($monthKeys) > 0)
                    <section class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">Activity</div>
                                <h2 class="mt-2 text-lg font-bold text-slate-950">Monthly Evaluation Volume</h2>
                            </div>
                            <flux:badge color="sky">{{ count($monthKeys) }} months</flux:badge>
                        </div>
                        <div class="mt-5 h-72">
                            <canvas data-scholar-chart="monthly"></canvas>
                        </div>
                    </section>
                @endif

                @foreach ($semesters as $i => $sem)
                    <section class="rounded-lg border border-white/80 bg-white/86 p-5 shadow-[0_18px_50px_rgba(15,23,42,0.07)] backdrop-blur" wire:key="semester-{{ $selected['record_id'] }}-{{ $sem['slug'] }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-sky-700">{{ $sem['label'] }} Semester</div>
                                <h2 class="mt-2 text-lg font-bold text-slate-950">Evaluation Summary</h2>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($sem['leadership'] !== null)
                                    <flux:badge color="violet">Leadership {{ $sem['leadership'] }}/10</flux:badge>
                                @endif
                                @if ($sem['final_score'] !== null)
                                    <flux:badge color="blue">Final {{ number_format($sem['final_score'], 2) }}</flux:badge>
                                @endif
                                <flux:badge color="zinc">{{ number_format($sem['total']) }} evals</flux:badge>
                            </div>
                        </div>

                        @if ($sem['total'] === 0)
                            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                                No evaluations recorded this semester.
                            </div>
                        @else
                            <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white/90">
                                    <flux:table>
                                        <flux:table.columns>
                                            <flux:table.column class="bg-slate-50/90 ps-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Category</flux:table.column>
                                            <flux:table.column class="bg-slate-50/90 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="end">Evals</flux:table.column>
                                            <flux:table.column class="bg-slate-50/90 pe-4 text-xs font-bold uppercase tracking-[0.18em] text-slate-500" align="end">Avg</flux:table.column>
                                        </flux:table.columns>
                                        <flux:table.rows>
                                            @foreach ($sem['category_keys'] as $j => $catKey)
                                                <flux:table.row class="transition hover:bg-slate-50/80" wire:key="semester-{{ $sem['slug'] }}-{{ $catKey }}">
                                                    <flux:table.cell class="ps-4 font-semibold text-slate-900">{{ $sem['category_labels'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="font-medium tabular-nums text-slate-600" align="end">{{ $sem['counts'][$j] }}</flux:table.cell>
                                                    <flux:table.cell class="pe-4 tabular-nums" align="end">
                                                        @if ($sem['averages'][$j] !== null)
                                                            <span class="font-semibold text-slate-950">{{ number_format($sem['averages'][$j], 1) }}</span>
                                                            <span class="text-xs text-slate-400">/100</span>
                                                        @else
                                                            <span class="text-slate-400">-</span>
                                                        @endif
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-white/90 p-4">
                                    <div class="mb-3 text-sm font-semibold text-slate-700">Evaluations by Category</div>
                                    <div class="h-56">
                                        <canvas data-scholar-chart="semester" data-semester-index="{{ $i }}"></canvas>
                                    </div>
                                </div>
                            </div>

                            @php $hasDates = collect($sem['dates'])->some(fn ($entries) => count($entries) > 0); @endphp
                            @if ($hasDates)
                                <div class="mt-5 rounded-lg border border-slate-200 bg-white/90 p-5">
                                    <div class="mb-4 text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Evaluation Dates by Category</div>
                                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($sem['category_keys'] as $j => $catKey)
                                            @if (! empty($sem['dates'][$catKey]))
                                                <div class="min-w-0">
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <span class="size-2 rounded-full bg-sky-500"></span>
                                                        <p class="text-sm font-semibold text-slate-800">{{ $sem['category_labels'][$j] }}</p>
                                                    </div>
                                                    <ul class="space-y-1.5">
                                                        @foreach ($sem['dates'][$catKey] as $entry)
                                                            <li class="text-sm leading-5 text-slate-600">{{ $entry }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (count($sem['comments']) > 0)
                                <div class="mt-5 rounded-lg border border-slate-200 bg-white/90 p-5">
                                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="text-[0.72rem] font-bold uppercase tracking-[0.3em] text-slate-500">Faculty Comments</div>
                                        <flux:badge color="zinc">{{ $sem['comments_count'] }}</flux:badge>
                                    </div>

                                    <div class="space-y-4">
                                        @foreach ($sem['comments'] as $comment)
                                            <article class="border-l-2 border-sky-200 pl-4">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="text-sm font-semibold text-slate-900">{{ $comment['faculty'] }}</span>
                                                    @if ($comment['date'] !== '')
                                                        <span class="text-sm text-slate-400">{{ $comment['date'] }}</span>
                                                    @endif
                                                    @if ($comment['category'] !== '')
                                                        <flux:badge size="sm" color="zinc">{{ $comment['category'] }}</flux:badge>
                                                    @endif
                                                </div>
                                                <p class="mt-2 text-base leading-7 text-slate-700">{{ $comment['comment'] }}</p>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </section>
                @endforeach
            </div>
        </section>
    @endif

    <script type="application/json" data-scholar-chart-payload>@json($chartPayload)</script>
</div>
