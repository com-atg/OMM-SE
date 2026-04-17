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
     * Fetch all completed evaluation records for a given scholar and semester.
     * scholar_name is the raw coded value (e.g. '1', '2', …).
     * semester is the raw coded value ('1' = Spring, '2' = Fall).
     * Returns empty array if either code fails validation.
     */
    public function getScholarEvals(string $scholarName, string $semester): array
    {
        if (! preg_match('/^\d+$/', $scholarName) || ! preg_match('/^[12]$/', $semester)) {
            return [];
        }

        return Redcap_lib::exportRecords(
            format: 'json',
            type: 'flat',
            rawOrLabel: 'raw',
            filterLogic: "[scholar_name]='{$scholarName}' and [semester]='{$semester}'",
            returnAs: 'array',
            url: $this->url,
            token: $this->token,
        );
    }

    /**
     * Resolve the full name label for a scholar_name coded value.
     * Coded values: 1=Catherine Chin, 2=Lea Dalco, 3=Grace Durbin,
     *               4=Ian Nevers, 5=Elianna Sanchez
     */
    public function resolveScholarName(string $code): string
    {
        $names = [
            '1' => 'Catherine Chin',
            '2' => 'Lea Dalco',
            '3' => 'Grace Durbin',
            '4' => 'Ian Nevers',
            '5' => 'Elianna Sanchez',
        ];

        return $names[$code] ?? '';
    }
}
