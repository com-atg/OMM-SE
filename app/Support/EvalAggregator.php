<?php

namespace App\Support;

use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Log;

class EvalAggregator
{
    public const SEMESTER_MAP = ['1' => 'spring', '2' => 'fall'];

    /**
     * Aggregate a flat list of source eval records (for one scholar+semester)
     * into destination-shaped fields plus a per-category summary.
     *
     * @param  array<int,array<string,mixed>>  $evals
     * @return array{fields: array<string,mixed>, by_category: array<string,array{nu:int,avg:float|null}>, semester: string}
     */
    public static function aggregate(array $evals, string $semester): array
    {
        $sums = [];
        $counts = [];
        $commentsList = [];

        foreach ($evals as $eval) {
            $category = $eval['eval_category'] ?? '';
            $destKey = RedcapSourceService::DEST_CATEGORY[$category] ?? null;
            $scoreField = RedcapSourceService::SCORE_FIELDS[$category] ?? null;

            if ($destKey && $scoreField && isset($eval[$scoreField]) && $eval[$scoreField] !== '') {
                $score = (float) $eval[$scoreField];

                if ($score < 0.0 || $score > 100.0) {
                    Log::warning("EvalAggregator: score {$score} out of range for {$scoreField}, skipping.");

                    continue;
                }

                $sums[$destKey] = ($sums[$destKey] ?? 0.0) + $score;
                $counts[$destKey] = ($counts[$destKey] ?? 0) + 1;
            }

            if (! empty($eval['comments'])) {
                $faculty = $eval['faculty'] ?? 'Faculty';
                $commentsList[] = "[{$faculty}]: {$eval['comments']}";
            }
        }

        $fields = [];
        $byCategory = [];

        foreach (RedcapSourceService::DEST_CATEGORY as $destKey) {
            $nu = $counts[$destKey] ?? 0;
            $avg = $nu > 0 ? round($sums[$destKey] / $nu, 2) : null;

            $fields["{$semester}_nu_{$destKey}"] = $nu;
            if ($avg !== null) {
                $fields["{$semester}_avg_{$destKey}"] = $avg;
            }

            $byCategory[$destKey] = ['nu' => $nu, 'avg' => $avg];
        }

        $commentCount = count($commentsList);
        $fields["{$semester}_nu_comments"] = $commentCount;
        if ($commentCount > 0) {
            $fields["{$semester}_comments"] = implode("\n\n", $commentsList);
        }

        return [
            'fields' => $fields,
            'by_category' => $byCategory,
            'semester' => $semester,
        ];
    }
}
