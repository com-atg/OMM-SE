<?php

namespace App\Http\Controllers;

use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private const SEMESTERS = ['spring', 'fall'];

    private const HISTOGRAM_BUCKETS = [
        ['label' => '<60', 'min' => 0.0, 'max' => 60.0],
        ['label' => '60–69', 'min' => 60.0, 'max' => 70.0],
        ['label' => '70–79', 'min' => 70.0, 'max' => 80.0],
        ['label' => '80–89', 'min' => 80.0, 'max' => 90.0],
        ['label' => '90–100', 'min' => 90.0, 'max' => 100.01],
    ];

    public function __invoke(RedcapDestinationService $destination): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user && $user->isStudent()) {
            return redirect()->route('scholar');
        }

        abort_unless($user && $user->canViewAllScholars(), 403);

        $stats = Cache::remember('dashboard:stats', now()->addMinutes(10), function () use ($destination) {
            try {
                $records = $destination->getAllScholarRecords();
            } catch (\Throwable $e) {
                Log::error('DashboardController: failed to fetch destination records.', ['error' => $e->getMessage()]);
                $records = [];
            }

            return $this->buildStats($records);
        });

        return view('dashboard', ['stats' => $stats]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<string,mixed>
     */
    private function buildStats(array $records): array
    {
        $categories = RedcapSourceService::DEST_CATEGORY;
        $labels = RedcapSourceService::CATEGORY_LABELS;

        $totalScholars = count($records);
        $totalEvals = 0;
        $scoreSum = 0.0;
        $scoreWeight = 0;
        $scholarsWithAnyEval = 0;

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
            $scholarHasAnyEval = false;

            foreach ($categories as $catKey) {
                $scholarHasCategory = false;

                foreach (self::SEMESTERS as $sem) {
                    $nu = (int) ($record["{$sem}_nu_{$catKey}"] ?? 0);
                    $avgRaw = $record["{$sem}_avg_{$catKey}"] ?? '';

                    if ($nu > 0) {
                        $countByCatSem[$catKey][$sem] += $nu;
                        $totalEvals += $nu;
                        $scholarHasAnyEval = true;
                        $scholarHasCategory = true;

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

                if ($scholarHasCategory) {
                    $coverageByCat[$catKey]++;
                }
            }

            if ($scholarHasAnyEval) {
                $scholarsWithAnyEval++;
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
            $coveragePct[] = $totalScholars > 0
                ? round($coverageByCat[$catKey] / $totalScholars * 100, 1)
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
            'has_scholars' => $totalScholars > 0,
            'has_evals' => $totalEvals > 0,
            'kpis' => [
                'total_scholars' => $totalScholars,
                'total_evals' => $totalEvals,
                'overall_avg' => $scoreWeight > 0 ? round($scoreSum / $scoreWeight, 2) : null,
                'scholars_evaluated' => $scholarsWithAnyEval,
                'scholars_without_evals' => max(0, $totalScholars - $scholarsWithAnyEval),
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
