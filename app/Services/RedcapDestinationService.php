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
     * Find a scholar record by email address. Case-insensitive.
     * Returns null if no scholar in the destination project has this email.
     */
    public function findScholarByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        foreach ($this->getAllScholarRecords() as $record) {
            if (strtolower(trim((string) ($record['email'] ?? ''))) === $email) {
                return $record;
            }
        }

        return null;
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

    /**
     * Fetch all scholar records from the destination project.
     * Cached for 10 minutes to balance freshness with REDCap API load.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllScholarRecords(int $cacheMinutes = 10): array
    {
        return Cache::remember('destination:all_scholars', now()->addMinutes($cacheMinutes), function () {
            $records = Redcap_lib::exportRecords(
                format: 'json',
                type: 'flat',
                rawOrLabel: 'raw',
                returnAs: 'array',
                url: $this->url,
                token: $this->token,
            );

            return is_array($records) ? $records : [];
        });
    }

    /**
     * Build a datatelid → scholar record map from the cached roster.
     *
     * @return array<string,array<string,mixed>>
     */
    public function scholarMapByDatatelId(): array
    {
        $map = [];
        foreach ($this->getAllScholarRecords() as $record) {
            $datatelId = (string) ($record['datatelid'] ?? '');
            if ($datatelId !== '') {
                $map[$datatelId] = $record;
            }
        }

        return $map;
    }
}
