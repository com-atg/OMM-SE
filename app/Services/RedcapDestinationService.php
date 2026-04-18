<?php

namespace App\Services;

use App\Models\Redcap_lib;
use Illuminate\Support\Facades\Cache;

/**
 * Wraps Redcap_lib for the destination scholar list project (REDCAP_TOKEN).
 */
class RedcapDestinationService
{
    private string $token;

    private string $url;

    public function __construct()
    {
        $this->token = config('redcap.token');
        $this->url = config('redcap.url');
    }

    /**
     * Find a scholar record by datatelid (the value stored in the source 'student' SQL field).
     * Caches for 1 hour since the scholar roster changes infrequently.
     */
    public function findScholarByDatatelId(string $datatelId): ?array
    {
        $cacheKey = 'scholar:datatelid:'.$datatelId;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($datatelId) {
            $records = Redcap_lib::exportRecords(
                format: 'json',
                type: 'flat',
                fields: 'record_id,datatelid,first_name,last_name,goes_by,email',
                rawOrLabel: 'raw',
                filterLogic: "[datatelid]='{$datatelId}'",
                returnAs: 'array',
                url: $this->url,
                token: $this->token,
            );

            foreach ($records as $record) {
                if (($record['datatelid'] ?? '') === $datatelId) {
                    return $record;
                }
            }

            return null;
        });
    }

    /**
     * Fetch a single scholar record by record_id (full record).
     */
    public function getScholarRecord(string $recordId): array
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
     * Import (upsert) a scholar aggregate record back into the destination project.
     * $data must contain 'record_id' plus the fields to update.
     */
    public function updateScholarRecord(array $data): string
    {
        return Redcap_lib::importRecords(
            data: json_encode([$data]),
            format: 'json',
            type: 'flat',
            overwriteBehavior: 'normal',
            url: $this->url,
            token: $this->token,
        );
    }
}
