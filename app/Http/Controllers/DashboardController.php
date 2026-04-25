<?php

namespace App\Http\Controllers;

use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private const CACHE_KEY = 'dashboard:stats:v3';

    private const FALLBACK_CACHE_KEY = 'dashboard:stats:last-success';

    private const SEMESTERS = ['spring', 'fall'];

    private const HISTOGRAM_BUCKETS = [
        ['label' => '<60', 'min' => 0.0, 'max' => 60.0],
        ['label' => '60–69', 'min' => 60.0, 'max' => 70.0],
        ['label' => '70–79', 'min' => 70.0, 'max' => 80.0],
        ['label' => '80–89', 'min' => 80.0, 'max' => 90.0],
        ['label' => '90–100', 'min' => 90.0, 'max' => 100.01],
    ];

    public function __invoke(RedcapDestinationService $destination, RedcapSourceService $source): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->isStudent()) {
            return redirect()->route('student');
        }

        abort_unless($user && $user->canViewDashboard(), 403);

        if ($user->isFaculty()) {
            return view('dashboard', [
                'stats' => $this->buildStats(
                    $this->destinationShapedRecordsForFaculty($source, $user)
                ),
            ]);
        }

        $stats = Cache::get(self::CACHE_KEY);

        if ($stats === null) {
            try {
                $records = $destination->getAllStudentRecords();
                $stats = $this->buildStats($records);

                Cache::put(self::CACHE_KEY, $stats, now()->addMinutes(10));
                Cache::put(self::FALLBACK_CACHE_KEY, $stats, now()->addDay());
            } catch (\Throwable $e) {
                Log::error('DashboardController: failed to fetch destination records.', ['error' => $e->getMessage()]);

                $stats = Cache::get(self::FALLBACK_CACHE_KEY);

                if ($stats !== null) {
                    $stats['fetch_error'] = 'Unable to refresh dashboard data. Showing the most recent cached snapshot.';
                    $stats['is_stale'] = true;
                } else {
                    $stats = $this->buildStats([], 'Unable to connect to REDCap. Check the REDCAP_URL and REDCAP_TOKEN configuration.');
                }
            }
        }

        return view('dashboard', ['stats' => $stats]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function destinationShapedRecordsForFaculty(RedcapSourceService $source, User $user): array
    {
        $sourceToken = ProjectMapping::latestSourceProject()?->redcap_token;
        $records = collect($source->getCompletedEvaluationRecords(token: $sourceToken))
            ->filter(fn (array $record): bool => $this->recordBelongsToFaculty($record, $user));

        $studentRecords = [];

        foreach ($records->groupBy(fn (array $record): string => trim((string) ($record['student'] ?? '')).'|'.trim((string) ($record['semester'] ?? ''))) as $key => $group) {
            [$studentId, $semesterCode] = explode('|', $key);
            $semester = EvalAggregator::SEMESTER_MAP[$semesterCode] ?? null;

            if ($studentId === '' || $semester === null) {
                continue;
            }

            $studentRecords[$studentId] ??= ['record_id' => $studentId];
            $studentRecords[$studentId] = array_merge(
                $studentRecords[$studentId],
                EvalAggregator::aggregate($group->values()->all(), $semester)['fields'],
            );
        }

        return array_values($studentRecords);
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

        // Per-category, per-semester totals.
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

        // Shape for charts.
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
                'spring' => $volumeBySemester['spring'],
                'fall' => $volumeBySemester['fall'],
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
