<?php

namespace App\Services;

use App\Models\Redcap_lib;
use App\Support\SemesterSlot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wraps Redcap_lib for the shared source evaluation project. The active source
 * project's token is resolved from project_mappings (single active row) and
 * passed explicitly to every method.
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
     * Fetch all completed evaluation records for a given student (datatelid),
     * semester code, and calendar year of [date_lab]. Year filtering happens in
     * PHP because [date_lab] is a free-form text field.
     *
     * $datatelId is the raw value from the source 'student' SQL field (numeric datatelid).
     * $semester  is the raw coded value ('1' = Spring, '2' = Fall).
     * $year      is the four-digit calendar year of the eval (matches year of [date_lab]).
     * Returns empty array if any input fails validation.
     */
    public function getStudentEvals(string $datatelId, string $semester, int $year, string $token): array
    {
        if (! preg_match('/^\d+$/', $datatelId) || ! preg_match('/^[12]$/', $semester)) {
            return [];
        }

        $records = Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            rawOrLabel: 'raw',
            filterLogic: "[student]='{$datatelId}' and [semester]='{$semester}'",
            returnAs: 'array',
            url: $this->url,
            token: $token,
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(
            $records,
            fn (array $record): bool => SemesterSlot::yearFromDate((string) ($record['date_lab'] ?? '')) === $year,
        ));
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
