<?php

use App\Enums\WeightCategory;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'id', except: '')]
    public string $selectedId = '';

    public bool $lockSelection = false;

    public ?string $shareableUrl = null;

    public ?int $selectedGraduationYear = null;

    public const SESSION_KEY = 'academic_year_filter';

    private const SEMESTERS = ['spring' => 'Spring', 'fall' => 'Fall'];

    public function mount(?string $initialSelectedId = null, bool $lockSelection = false, ?string $shareableUrl = null): void
    {
        $this->selectedId = (string) ($initialSelectedId ?? $this->selectedId);
        $this->lockSelection = $lockSelection;
        $this->shareableUrl = $shareableUrl;
        $this->selectedGraduationYear = $this->resolveInitialGraduationYear();
    }

    public function updatedSelectedGraduationYear(): void
    {
        session([self::SESSION_KEY => $this->selectedGraduationYear]);
        $this->selectedId = '';
    }

    public function render(): View
    {
        $destination = app(RedcapDestinationService::class);

        $availableMappings = ProjectMapping::query()
            ->orderByDesc('graduation_year')
            ->get(['id', 'academic_year', 'graduation_year']);

        $year = $this->selectedGraduationYear;

        if ($this->lockSelection || $availableMappings->count() < 2) {
            $records = $destination->getAllStudentRecords();
        } else {
            $records = $year !== null
                ? $destination->getStudentsByGraduationYear($year)
                : $destination->getAllStudentRecords();
        }

        $selectedRecord = $this->resolveRecord($records, $this->selectedId);
        $selected = $selectedRecord ? $this->selectedStudent($selectedRecord) : null;
        $scoreFormulas = $selectedRecord ? $this->scoreFormulasFromDb($year) : [];
        $semesters = $selectedRecord ? $this->buildSemesters($selectedRecord, $scoreFormulas) : [];

        $mapping = $year !== null
            ? ProjectMapping::byGraduationYear($year)
            : ProjectMapping::current();

        return view('components.⚡student-detail', [
            'roster' => $this->lockSelection ? [] : $this->roster($records),
            'selected' => $selected,
            'semesters' => $semesters,
            'finalGrade' => $this->finalGrade($semesters),
            'academicYear' => $mapping?->academic_year,
            'availableMappings' => $availableMappings,
        ]);
    }

    private function resolveInitialGraduationYear(): ?int
    {
        $available = ProjectMapping::query()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($y) => (int) $y)
            ->all();

        if ($available === []) {
            return null;
        }

        $stored = (int) session(self::SESSION_KEY, 0);
        if ($stored > 0 && in_array($stored, $available, true)) {
            return $stored;
        }

        return $available[0];
    }

    /**
     * @return array<string,array{field:string,components:array<int,array{field:string,label:string,coefficient:float,max_value:float,max_points:float,weight_percent:float}>}>
     */
    private function scoreFormulasFromDb(?int $graduationYear = null): array
    {
        $mapping = $graduationYear !== null
            ? ProjectMapping::byGraduationYear($graduationYear)
            : ProjectMapping::current();

        if (! $mapping) {
            return [];
        }

        /** @var Collection<string,\App\Models\CategoryWeight> $weights */
        $weights = $mapping->categoryWeights()->get()->keyBy(fn ($w) => $w->category->value);

        if ($weights->isEmpty()) {
            return [];
        }

        $components = collect(WeightCategory::cases())
            ->filter(fn (WeightCategory $cat): bool => $weights->has($cat->value))
            ->map(function (WeightCategory $cat) use ($weights): array {
                $weight = (float) $weights->get($cat->value)->weight;
                $maxValue = $cat === WeightCategory::Leadership ? 10.0 : 100.0;

                return [
                    'field' => $cat->value,
                    'label' => $cat->label(),
                    'coefficient' => round($weight / 100, 4),
                    'max_value' => $maxValue,
                    'max_points' => $weight,
                    'weight_percent' => $weight,
                ];
            })
            ->values()
            ->all();

        return [
            'spring' => ['field' => 'spring_final_score', 'components' => $components],
            'fall' => ['field' => 'fall_final_score', 'components' => $components],
        ];
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
     * @param  array<string,array<string,mixed>>  $scoreFormulas
     * @return array<int,array<string,mixed>>
     */
    private function buildSemesters(array $record, array $scoreFormulas = []): array
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
                'score_formula' => $scoreFormulas[$slug] ?? null,
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
$chartSemesters = collect($semesters)
    ->map(function (array $sem): array {
        if (! empty($sem['score_formula'])) {
            $sem['score_formula'] = [
                'field' => $sem['score_formula']['field'] ?? null,
                'components' => $sem['score_formula']['components'] ?? [],
            ];
        }

        return $sem;
    })
    ->all();
$chartPayload = [
    'semesters' => $chartSemesters,
    'categoryLabels' => $categoryLabels,
    'categoryKeys' => $categoryKeys,
    'mergedMonthly' => $mergedMonthly,
    'monthKeys' => $monthKeys,
];
?>

<div class="flex flex-col gap-7">
    @unless ($lockSelection)
        <section class="rounded-lg border border-[#d8e3fa] bg-white/90 p-5 shadow-[0_14px_38px_rgba(26,54,93,0.06)] backdrop-blur">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                @if ($availableMappings->count() >= 2)
                    <flux:select
                        class="max-w-[14rem]"
                        wire:model.live="selectedGraduationYear"
                        label="Academic Year"
                    >
                        @foreach ($availableMappings as $am)
                            <flux:select.option value="{{ $am->graduation_year }}" wire:key="student-ay-option-{{ $am->id }}">
                                {{ $am->academic_year }} (Class of {{ $am->graduation_year }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select
                    class="max-w-sm"
                    wire:model.live="selectedId"
                    label="Choose a student"
                >
                    <flux:select.option value="">Select a student...</flux:select.option>
                    @foreach ($roster as $student)
                        <flux:select.option value="{{ $student['record_id'] }}" wire:key="student-option-{{ $student['record_id'] }}">
                            {{ $student['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
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
                <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="shrink-0 overflow-hidden rounded-lg border border-[#cfdaf1] bg-[#e7eeff]">
                            @if ($selected['photo_url'])
                                <img
                                    src="{{ $selected['photo_url'] }}"
                                    alt="{{ $selected['name'] }}"
                                    class="size-40 object-cover"
                                    onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('grid');"
                                >
                                <div class="hidden size-40 place-items-center bg-[#002045] text-4xl font-bold text-white">
                                    {{ $selectedInitials }}
                                </div>
                            @else
                                <div class="grid size-40 place-items-center bg-[#002045] text-4xl font-bold text-white">
                                    {{ $selectedInitials }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-[#d6e3ff] px-3 py-1 text-[0.68rem] font-bold uppercase tracking-[0.22em] text-[#001b3c]">Student Profile</span>
                            </div>
                            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-[#111c2c] sm:text-4xl">{{ $selected['name'] }}</h2>
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[#43474e]">
                                <span class="inline-flex items-center gap-1.5">
                                    <flux:icon.academic-cap variant="mini" class="size-4 text-[#455f88]" />
                                    Final Grade:
                                    <strong class="font-semibold text-[#111c2c]">{{ $finalGrade ? number_format($finalGrade['score'], 2) : 'Pending' }}</strong>
                                </span>
                            </div>
                            @unless ($finalGrade)
                                <p class="mt-2 text-sm font-medium text-[#74777f]">Final grade is not available yet.</p>
                            @endunless
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 sm:min-w-[460px] sm:grid-cols-4">
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Evals</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($totalEvaluations) }}</dd>
                        </div>
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Comments</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-[#111c2c]">{{ number_format($totalComments) }}</dd>
                        </div>
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f4fbfa] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#006a63]">Leadership</dt>
                            <dd class="mt-1 text-xl font-semibold tabular-nums text-[#111c2c]">
                                {{ $hasLeadershipPoints ? number_format($leadershipEarned).'/'.number_format($leadershipMax) : 'Pending' }}
                            </dd>
                            <div class="mt-2 flex justify-center gap-2 text-[0.68rem] font-semibold uppercase tracking-[0.08em] text-[#74777f]">
                                @foreach ($leadershipRows as $row)
                                    <span>{{ $row['label'] }} {{ $row['points'] ?? '-' }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="rounded-lg border border-[#e2e8f0] bg-[#f9f9ff] p-4 text-center">
                            <dt class="text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#455f88]">Status</dt>
                            <dd class="mt-1 text-sm font-semibold text-[#111c2c]">{{ $finalGrade ? $finalGrade['label'] : 'Pending' }}</dd>
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

                    @php $weightComponents = $semesters[0]['score_formula']['components'] ?? []; @endphp
                    <section class="rounded-lg border border-[#d8e3fa] bg-white/92 p-6 shadow-[0_16px_42px_rgba(26,54,93,0.06)] backdrop-blur">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-[0.72rem] font-bold uppercase tracking-[0.24em] text-[#455f88]">Score Weights</div>
                                <h2 class="mt-2 text-lg font-semibold text-[#111c2c]">Weight Distribution</h2>
                            </div>
                            @if ($academicYear)
                                <span class="rounded-full bg-[#d6e3ff] px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-[#001b3c]">{{ $academicYear }}</span>
                            @endif
                        </div>

                        @if (! empty($weightComponents))
                            <div class="mt-5 mx-auto max-w-xs">
                                <div class="h-64">
                                    <canvas data-student-chart="weights" data-semester-index="0"></canvas>
                                </div>
                            </div>
                        @else
                            <div class="mt-5 rounded-lg border border-dashed border-[#c4c6cf] bg-white p-5 text-sm leading-6 text-[#74777f]">
                                No category weights configured for this academic year. Add them in Settings → Project Mapping → Weights.
                            </div>
                        @endif
                    </section>

                    @foreach ($semesters as $i => $sem)
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
                                    @if ($sem['final_score'] !== null)
                                        <flux:badge color="blue">Final {{ number_format($sem['final_score'], 2) }}</flux:badge>
                                    @endif
                                    <flux:badge color="zinc">{{ number_format($sem['total']) }} evals</flux:badge>
                                </div>
                            </div>

                            @if ($sem['total'] === 0)
                                <div class="mt-5 rounded-lg border border-dashed border-[#c4c6cf] bg-[#f9f9ff] p-8 text-center text-sm text-[#74777f]">
                                    <flux:icon.no-symbol variant="mini" class="mx-auto mb-3 size-6 text-[#98a2b3]" />
                                    No evaluations recorded this semester.
                                </div>
                            @else
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
