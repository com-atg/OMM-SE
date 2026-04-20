<?php

namespace App\Services;

use App\Models\Redcap_lib;

/**
 * Wraps Redcap_lib for the current academic year's source evaluation project.
 * The source project is recreated each year — update REDCAP_SOURCE_TOKEN in .env annually.
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
    public function getRecord(string $recordId): array
    {
        $result = Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            records: $recordId,
            rawOrLabel: 'raw',
            returnAs: 'array',
            url: $this->url,
            token: $this->token,
        );

        return $result[0] ?? [];
    }

    /**
     * Fetch all completed evaluation records for a given student (datatelid) and semester.
     * $datatelId is the raw value from the source 'student' SQL field (numeric datatelid).
     * semester is the raw coded value ('1' = Spring, '2' = Fall).
     * Returns empty array if either value fails validation.
     */
    public function getScholarEvals(string $datatelId, string $semester): array
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
            token: $this->token,
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
}
