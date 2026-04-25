<?php

namespace App\Services;

use App\Models\Redcap_lib;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wraps Redcap_lib for the current academic year's source evaluation project.
 * The source project is recreated each year. Webhooks may provide a PID-specific
 * token resolved from project mappings; otherwise REDCAP_SOURCE_TOKEN is used.
 */
class RedcapSourceService
{
    private string $token;

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
        $this->token = config('redcap.source_token');
        $this->url = config('redcap.url');
    }

    /**
     * Fetch a single evaluation record by record_id.
     * Returns the first record as an array (raw values + calculated fields).
     */
    public function getRecord(string $recordId, ?string $token = null): array
    {
        $result = Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            records: $recordId,
            rawOrLabel: 'raw',
            returnAs: 'array',
            url: $this->url,
            token: $token ?? $this->token,
        );

        return $result[0] ?? [];
    }

    /**
     * Fetch all completed evaluation records for a given student (datatelid) and semester.
     * $datatelId is the raw value from the source 'student' SQL field (numeric datatelid).
     * semester is the raw coded value ('1' = Spring, '2' = Fall).
     * Returns empty array if either value fails validation.
     */
    public function getStudentEvals(string $datatelId, string $semester, ?string $token = null): array
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
            token: $token ?? $this->token,
        );
    }

    /**
     * Fetch all records from an arbitrary source project by token.
     * Used by bulk processing where the token is resolved per-PID from env.
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
    public function getCompletedEvaluationRecords(int $cacheMinutes = 5, ?string $token = null): array
    {
        $sourceToken = $token ?? $this->token;

        if ($sourceToken === '') {
            return [];
        }

        $cacheKey = 'source:completed_evaluations:'.Str::substr(hash('sha256', $sourceToken), 0, 16);

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($sourceToken): array {
            return collect($this->fetchAllRecords($sourceToken))
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
