<?php

namespace App\Support;

use App\Services\RedcapSourceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class EvalAggregator
{
    /**
     * Aggregate a flat list of source eval records (for one student + slot)
     * into destination-shaped fields plus a per-category summary.
     *
     * Field names use the slot key ('sem1' … 'sem4') as a prefix:
     *   sem{n}_nu_{cat}, sem{n}_avg_{cat}, sem{n}_dates_{cat},
     *   sem{n}_nu_comments, sem{n}_comments.
     *
     * Dates field format  : "Faculty Name, M/D/YYYY; Faculty Name, M/D/YYYY"
     * Comments field format: "Faculty; M/D/YYYY; Category; Comment text\n…"
     *
     * @param  array<int,array<string,mixed>>  $evals
     * @return array{fields: array<string,mixed>, by_category: array<string,array{nu:int,avg:float|null}>, slot_key: string}
     */
    public static function aggregate(array $evals, string $slotKey): array
    {
        $sums = [];
        $counts = [];
        /** @var array<string,list<string>> $dateEntries destKey → ["Faculty, M/D/YYYY", …] */
        $dateEntries = [];
        /** @var list<string> $commentLines */
        $commentLines = [];

        foreach ($evals as $eval) {
            $category = $eval['eval_category'] ?? '';
            $destKey = RedcapSourceService::DEST_CATEGORY[$category] ?? null;
            $scoreField = RedcapSourceService::SCORE_FIELDS[$category] ?? null;
            $faculty = trim((string) ($eval['faculty'] ?? 'Faculty'));
            $rawDate = trim((string) ($eval['date_lab'] ?? ''));
            $displayDate = self::formatDate($rawDate);

            if ($destKey && $scoreField && isset($eval[$scoreField]) && $eval[$scoreField] !== '') {
                $score = (float) $eval[$scoreField];

                if ($score < 0.0 || $score > 100.0) {
                    Log::warning("EvalAggregator: score {$score} out of range for {$scoreField}, skipping.");

                    continue;
                }

                $sums[$destKey] = ($sums[$destKey] ?? 0.0) + $score;
                $counts[$destKey] = ($counts[$destKey] ?? 0) + 1;
                $dateEntries[$destKey][] = "{$faculty}, {$displayDate}";
            }

            if (! empty($eval['comments'])) {
                $categoryLabel = RedcapSourceService::CATEGORY_LABELS[$category] ?? $category;
                $commentLines[] = "{$faculty}; {$displayDate}; {$categoryLabel}; {$eval['comments']}";
            }
        }

        $fields = [];
        $byCategory = [];

        foreach (RedcapSourceService::DEST_CATEGORY as $destKey) {
            $nu = $counts[$destKey] ?? 0;
            $avg = $nu > 0 ? round($sums[$destKey] / $nu, 2) : null;

            $fields["{$slotKey}_nu_{$destKey}"] = $nu;
            if ($avg !== null) {
                $fields["{$slotKey}_avg_{$destKey}"] = $avg;
            }

            if (! empty($dateEntries[$destKey])) {
                $fields["{$slotKey}_dates_{$destKey}"] = implode('; ', $dateEntries[$destKey]);
            }

            $byCategory[$destKey] = ['nu' => $nu, 'avg' => $avg];
        }

        $commentCount = count($commentLines);
        $fields["{$slotKey}_nu_comments"] = $commentCount;
        if ($commentCount > 0) {
            $fields["{$slotKey}_comments"] = implode("\n", $commentLines);
        }

        return [
            'fields' => $fields,
            'by_category' => $byCategory,
            'slot_key' => $slotKey,
        ];
    }

    /**
     * Parse a raw REDCap date string (MM-DD-YYYY or YYYY-MM-DD) and return M/D/YYYY.
     * Returns the original string unchanged if it cannot be parsed.
     */
    private static function formatDate(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->format('n/j/Y');
        } catch (\Throwable) {
            return $raw;
        }
    }
}
