<?php

namespace App\Services;

use App\Models\Redcap_lib;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wraps Redcap_lib for source evaluation projects. Source projects are recreated
 * each academic year; callers must resolve the per-AY token from project_mappings
 * and pass it explicitly to every method.
 */
class RedcapSourceService
{
    private string $url;

    /** Score field name keyed by eval_category code. */
    public const SCORE_FIELDS = [
        'A' => 'teaching_score',
        'B' => 'clinical_performance_score',
        'C' => 'research_total_score',
        'D' => 'didactic_total_score',
    ];

    /** Human-readable label keyed by eval_category code. */
    public const CATEGORY_LABELS = [
        'A' => 'Teaching',
        'B' => 'Clinic',
        'C' => 'Research',
        'D' => 'Didactics',
    ];

    /** Destination avg field suffix keyed by eval_category code. */
    public const DEST_CATEGORY = [
        'A' => 'teaching',
        'B' => 'clinic',
        'C' => 'research',
        'D' => 'didactics',
    ];

    public function __construct()
    {
        $this->url = config('redcap.url');
    }

    /**
     * Fetch a single evaluation record by record_id.
     * Returns the first record as an array (raw values + calculated fields).
     */
    public function getRecord(string $recordId, string $token): array
    {
        $result = Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            records: $recordId,
            rawOrLabel: 'raw',
            returnAs: 'array',
            url: $this->url,
            token: $token,
        );

        return $result[0] ?? [];
    }

    /**
     * Fetch all completed evaluation records for a given student (datatelid) and semester.
     * $datatelId is the raw value from the source 'student' SQL field (numeric datatelid).
     * semester is the raw coded value ('1' = Spring, '2' = Fall).
     * Returns empty array if either value fails validation.
     */
    public function getStudentEvals(string $datatelId, string $semester, string $token): array
    {
        if (! preg_match('/^\d+$/', $datatelId) || ! preg_match('/^[12]$/', $semester)) {
            return [];
        }

        return Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            rawOrLabel: 'raw',
            filterLogic: "[student]='{$datatelId}' and [semester]='{$semester}'",
            returnAs: 'array',
            url: $this->url,
            token: $token,
        );
    }

    /**
     * Fetch all records from a source project by token.
     * Token is resolved per-PID from project_mappings.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAllRecords(string $token): array
    {
        $records = Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            rawOrLabel: 'raw',
            returnAs: 'array',
            url: $this->url,
            token: $token,
        );

        return is_array($records) ? $records : [];
    }

    /**
     * Fetch completed evaluation records from the current source project.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCompletedEvaluationRecords(string $token, int $cacheMinutes = 5): array
    {
        if ($token === '') {
            return [];
        }

        $cacheKey = 'source:completed_evaluations:'.Str::substr(hash('sha256', $token), 0, 16);

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($token): array {
            return collect($this->fetchAllRecords($token))
                ->filter(fn (array $record): bool => $this->isCompletedEvaluationRecord($record))
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string,mixed>  $record
     */
    private function isCompletedEvaluationRecord(array $record): bool
    {
        $completionStatuses = collect($record)
            ->filter(fn (mixed $value, string $field): bool => str_ends_with($field, '_complete') && (string) $value !== '')
            ->map(fn (mixed $value): string => (string) $value);

        $hasRequiredFields = trim((string) ($record['student'] ?? '')) !== ''
            && trim((string) ($record['semester'] ?? '')) !== ''
            && trim((string) ($record['eval_category'] ?? '')) !== ''
            && trim((string) ($record['faculty'] ?? '')) !== '';

        if (! $hasRequiredFields) {
            return false;
        }

        if ($completionStatuses->isNotEmpty()) {
            return $completionStatuses->contains('2');
        }

        $scoreField = self::SCORE_FIELDS[(string) ($record['eval_category'] ?? '')] ?? null;

        return $scoreField !== null && trim((string) ($record[$scoreField] ?? '')) !== '';
    }
}
