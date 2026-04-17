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
     * Find a scholar record by first + last name.
     * Uses REDCap filterLogic to avoid exporting all records, and caches the
     * result for 1 hour since the scholar roster changes infrequently.
     */
    public function findScholarRecord(string $firstName, string $lastName): ?array
    {
        $cacheKey = 'scholar:'.strtolower($firstName).':'.strtolower($lastName);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($firstName, $lastName) {
            $filter = "[first_name]='{$firstName}' AND [last_name]='{$lastName}'";

            $records = Redcap_lib::exportRecords(
                format: 'json',
                type: 'flat',
                fields: 'record_id,first_name,last_name,goes_by,email',
                rawOrLabel: 'raw',
                filterLogic: $filter,
                returnAs: 'array',
                url: $this->url,
                token: $this->token,
            );

            foreach ($records as $record) {
                if (
                    strcasecmp($record['first_name'] ?? '', $firstName) === 0 &&
                    strcasecmp($record['last_name'] ?? '', $lastName) === 0
                ) {
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
