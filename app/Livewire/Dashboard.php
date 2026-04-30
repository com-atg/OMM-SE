<?php

namespace App\Livewire;

use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use App\Support\SemesterSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $activeOnly = true;

    public ?string $selectedBatch = null;

    public const SESSION_KEY_ACTIVE_ONLY = 'roster_filter:active_only';

    public const SESSION_KEY_BATCH = 'roster_filter:batch';

    private const CACHE_KEY_PREFIX = 'dashboard:stats:v4';

    private const FALLBACK_CACHE_KEY_PREFIX = 'dashboard:stats:last-success:v4';

    private const SEMESTERS = ['sem1', 'sem2', 'sem3', 'sem4'];

    private const HISTOGRAM_BUCKETS = [
        ['label' => '<60', 'min' => 0.0, 'max' => 60.0],
        ['label' => '60–69', 'min' => 60.0, 'max' => 70.0],
        ['label' => '70–79', 'min' => 70.0, 'max' => 80.0],
        ['label' => '80–89', 'min' => 80.0, 'max' => 90.0],
        ['label' => '90–100', 'min' => 90.0, 'max' => 100.01],
    ];

    public function mount(): void
    {
        $this->activeOnly = (bool) session(self::SESSION_KEY_ACTIVE_ONLY, true);
        $batch = (string) session(self::SESSION_KEY_BATCH, '');
        $this->selectedBatch = $batch !== '' ? $batch : null;
    }

    public function updatedActiveOnly(): void
    {
        session([self::SESSION_KEY_ACTIVE_ONLY => $this->activeOnly]);
    }

    public function updatedSelectedBatch(): void
    {
        session([self::SESSION_KEY_BATCH => $this->selectedBatch ?? '']);
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canViewDashboard(), 403);

        $destination = app(RedcapDestinationService::class);

        try {
            $availableBatches = $destination->availableBatches();
        } catch (\Throwable $e) {
            Log::error('Dashboard: failed to fetch available batches.', ['error' => $e->getMessage()]);
            $availableBatches = [];
        }

        if ($this->selectedBatch !== null && ! in_array($this->selectedBatch, $availableBatches, true)) {
            $this->selectedBatch = null;
            session([self::SESSION_KEY_BATCH => '']);
        }

        $mapping = ProjectMapping::activeSource();

        if ($user->isFaculty()) {
            $stats = $this->buildStats(
                $this->destinationShapedRecordsForFaculty($mapping, $user)
            );

            return view('livewire.dashboard', [
                'stats' => $stats,
                'availableBatches' => $availableBatches,
            ]);
        }

        $cacheKey = self::CACHE_KEY_PREFIX.':active='.($this->activeOnly ? '1' : '0').':batch='.($this->selectedBatch ?? 'all');
        $fallbackKey = self::FALLBACK_CACHE_KEY_PREFIX.':active='.($this->activeOnly ? '1' : '0').':batch='.($this->selectedBatch ?? 'all');

        $stats = Cache::get($cacheKey);

        if ($stats === null) {
            try {
                $records = $this->filterRecords($destination->getAllStudentRecords());
                $stats = $this->buildStats($records);

                Cache::put($cacheKey, $stats, now()->addMinutes(10));
                Cache::put($fallbackKey, $stats, now()->addDay());
            } catch (\Throwable $e) {
                Log::error('Dashboard: failed to fetch destination records.', ['error' => $e->getMessage()]);

                $stats = Cache::get($fallbackKey);

                if ($stats !== null) {
                    $stats['fetch_error'] = 'Unable to refresh dashboard data. Showing the most recent cached snapshot.';
                    $stats['is_stale'] = true;
                } else {
                    $stats = $this->buildStats([], 'Unable to connect to REDCap. Check the REDCAP_URL and REDCAP_TOKEN configuration.');
                }
            }
        }

        return view('livewire.dashboard', [
            'stats' => $stats,
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
     * @return array<int,array<string,mixed>>
     */
    private function destinationShapedRecordsForFaculty(?ProjectMapping $mapping, User $user): array
    {
        $sourceToken = (string) ($mapping?->redcap_token ?? '');
        $records = collect($sourceToken === '' ? [] : app(RedcapSourceService::class)->getCompletedEvaluationRecords($sourceToken))
            ->filter(fn (array $record): bool => $this->recordBelongsToFaculty($record, $user));

        $studentMap = $this->filterStudentMap(
            app(RedcapDestinationService::class)->studentMapByDatatelId()
        );

        $groups = [];

        foreach ($records as $record) {
            $studentId = trim((string) ($record['student'] ?? ''));
            $semesterCode = trim((string) ($record['semester'] ?? ''));
            $dateLab = trim((string) ($record['date_lab'] ?? ''));

            if ($studentId === '' || $semesterCode === '' || $dateLab === '') {
                continue;
            }

            $studentRecord = $studentMap[$studentId] ?? null;

            if (! $studentRecord) {
                continue;
            }

            $cohortTerm = trim((string) ($studentRecord['cohort_start_term'] ?? '')) ?: null;
            $cohortYearRaw = trim((string) ($studentRecord['cohort_start_year'] ?? ''));
            $cohortYear = $cohortYearRaw !== '' && ctype_digit($cohortYearRaw) ? (int) $cohortYearRaw : null;

            $slot = SemesterSlot::compute($semesterCode, $dateLab, $cohortTerm, $cohortYear);

            if ($slot === null) {
                continue;
            }

            $slotKey = SemesterSlot::slotKey($slot);
            $groups["{$studentId}|{$slotKey}"][] = $record;
        }

        $studentRecords = [];

        foreach ($groups as $key => $groupRecords) {
            [$studentId, $slotKey] = explode('|', $key);
            $studentRecords[$studentId] ??= ['record_id' => $studentId];
            $studentRecords[$studentId] = array_merge(
                $studentRecords[$studentId],
                EvalAggregator::aggregate($groupRecords, $slotKey)['fields'],
            );
        }

        return array_values($studentRecords);
    }

    /**
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<string,array<string,mixed>>
     */
    private function filterStudentMap(array $studentMap): array
    {
        return collect($studentMap)
            ->filter(function (array $record): bool {
                if ($this->activeOnly && (string) ($record['is_active'] ?? '') !== '1') {
                    return false;
                }

                if ($this->selectedBatch !== null && trim((string) ($record['batch'] ?? '')) !== $this->selectedBatch) {
                    return false;
                }

                return true;
            })
            ->all();
    }

    /**
     * @param  array<string,mixed>  $record
     */
    private function recordBelongsToFaculty(array $record, User $user): bool
    {
        $facultyEmail = strtolower(trim((string) ($record['faculty_email'] ?? '')));
        $facultyName = strtolower(trim((string) ($record['faculty'] ?? '')));

        return ($facultyEmail !== '' && $facultyEmail === strtolower($user->email))
            || ($facultyName !== '' && $facultyName === strtolower($user->name));
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<string,mixed>
     */
    private function buildStats(array $records, ?string $fetchError = null): array
    {
        $categories = RedcapSourceService::DEST_CATEGORY;
        $labels = RedcapSourceService::CATEGORY_LABELS;

        $totalStudents = count($records);
        $totalEvals = 0;
        $scoreSum = 0.0;
        $scoreWeight = 0;
        $studentsWithAnyEval = 0;

        $countByCatSem = [];
        $scoreSumByCatSem = [];
        $scoreCountByCatSem = [];
        $coverageByCat = [];
        $histogramByCat = [];

        foreach ($categories as $catKey) {
            foreach (self::SEMESTERS as $sem) {
                $countByCatSem[$catKey][$sem] = 0;
                $scoreSumByCatSem[$catKey][$sem] = 0.0;
                $scoreCountByCatSem[$catKey][$sem] = 0;
            }
            $coverageByCat[$catKey] = 0;
            $histogramByCat[$catKey] = array_fill(0, count(self::HISTOGRAM_BUCKETS), 0);
        }

        foreach ($records as $record) {
            $studentHasAnyEval = false;

            foreach ($categories as $catKey) {
                $studentHasCategory = false;

                foreach (self::SEMESTERS as $sem) {
                    $nu = (int) ($record["{$sem}_nu_{$catKey}"] ?? 0);
                    $avgRaw = $record["{$sem}_avg_{$catKey}"] ?? '';

                    if ($nu > 0) {
                        $countByCatSem[$catKey][$sem] += $nu;
                        $totalEvals += $nu;
                        $studentHasAnyEval = true;
                        $studentHasCategory = true;

                        if ($avgRaw !== '' && is_numeric($avgRaw)) {
                            $avg = (float) $avgRaw;
                            $scoreSumByCatSem[$catKey][$sem] += $avg * $nu;
                            $scoreCountByCatSem[$catKey][$sem] += $nu;
                            $scoreSum += $avg * $nu;
                            $scoreWeight += $nu;

                            $this->bucketScore($histogramByCat[$catKey], $avg);
                        }
                    }
                }

                if ($studentHasCategory) {
                    $coverageByCat[$catKey]++;
                }
            }

            if ($studentHasAnyEval) {
                $studentsWithAnyEval++;
            }
        }

        $categoryLabels = array_map(fn ($k) => $labels[array_search($k, $categories, true)] ?? ucfirst($k), $categories);
        $categoryKeys = array_values($categories);

        $avgByCategory = [];
        foreach ($categoryKeys as $catKey) {
            $sum = 0.0;
            $n = 0;
            foreach (self::SEMESTERS as $sem) {
                $sum += $scoreSumByCatSem[$catKey][$sem];
                $n += $scoreCountByCatSem[$catKey][$sem];
            }
            $avgByCategory[] = $n > 0 ? round($sum / $n, 2) : 0;
        }

        $volumeBySemester = [];
        foreach (self::SEMESTERS as $sem) {
            $row = [];
            foreach ($categoryKeys as $catKey) {
                $row[] = $countByCatSem[$catKey][$sem];
            }
            $volumeBySemester[$sem] = $row;
        }

        $coveragePct = [];
        foreach ($categoryKeys as $catKey) {
            $coveragePct[] = $totalStudents > 0
                ? round($coverageByCat[$catKey] / $totalStudents * 100, 1)
                : 0;
        }

        $histogramLabels = array_column(self::HISTOGRAM_BUCKETS, 'label');
        $histogramSeries = [];
        foreach ($categoryKeys as $catKey) {
            $histogramSeries[] = [
                'label' => $labels[array_search($catKey, $categories, true)] ?? ucfirst($catKey),
                'data' => $histogramByCat[$catKey],
            ];
        }

        return [
            'has_students' => $totalStudents > 0,
            'has_evals' => $totalEvals > 0,
            'kpis' => [
                'total_students' => $totalStudents,
                'total_evals' => $totalEvals,
                'overall_avg' => $scoreWeight > 0 ? round($scoreSum / $scoreWeight, 2) : null,
                'students_evaluated' => $studentsWithAnyEval,
                'students_without_evals' => max(0, $totalStudents - $studentsWithAnyEval),
            ],
            'category_labels' => array_values($categoryLabels),
            'avg_by_category' => $avgByCategory,
            'volume_by_semester' => [
                'labels' => array_values($categoryLabels),
                'sem1' => $volumeBySemester['sem1'],
                'sem2' => $volumeBySemester['sem2'],
                'sem3' => $volumeBySemester['sem3'],
                'sem4' => $volumeBySemester['sem4'],
            ],
            'coverage_pct' => $coveragePct,
            'histogram' => [
                'labels' => $histogramLabels,
                'series' => $histogramSeries,
            ],
            'fetch_error' => $fetchError,
            'is_stale' => false,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int,int>  $bucket
     */
    private function bucketScore(array &$bucket, float $score): void
    {
        foreach (self::HISTOGRAM_BUCKETS as $i => $range) {
            if ($score >= $range['min'] && $score < $range['max']) {
                $bucket[$i]++;

                return;
            }
        }
    }
}
