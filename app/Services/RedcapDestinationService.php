<?php

namespace App\Services;

use App\Models\Redcap_lib;
use Illuminate\Support\Facades\Cache;

/**
 * Wraps Redcap_lib for the destination student list project (REDCAP_TOKEN).
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
     * Find a student record by datatelid (the value stored in the source 'student' SQL field).
     * Caches for 1 hour since the student roster changes infrequently.
     */
    public function findStudentByDatatelId(string $datatelId): ?array
    {
        if (! preg_match('/^\d+$/', $datatelId)) {
            return null;
        }

        $cacheKey = 'student:datatelid:'.$datatelId;

        return Cache::remember($cacheKey, now()->addHour(), function () use ($datatelId) {
            $records = Redcap_lib::exportRecords(
                format: 'json',
                type: 'flat',
                fields: 'record_id,datatelid,first_name,last_name,goes_by,email,cohort_start_term,cohort_start_year,batch,is_active',
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
     * Find a student record by email address. Case-insensitive.
     * Returns null if no student in the destination project has this email.
     */
    public function findStudentByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        foreach ($this->getAllStudentRecords() as $record) {
            if (strtolower(trim((string) ($record['email'] ?? ''))) === $email) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Fetch a single student record by record_id (full record).
     */
    public function getStudentRecord(string $recordId): array
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
     * Import (upsert) a student aggregate record back into the destination project.
     * $data must contain 'record_id' plus the fields to update.
     */
    public function updateStudentRecord(array $data): string
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
     * Fetch all student records from the destination project.
     * Cached for 10 minutes to balance freshness with REDCap API load.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllStudentRecords(int $cacheMinutes = 10): array
    {
        return Cache::remember('destination:all_students', now()->addMinutes($cacheMinutes), function () {
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
     * Fetch destination student records filtered to is_active=1.
     * Filters client-side from the cached full roster so no extra REDCap call is made.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getActiveStudentRecords(): array
    {
        return collect($this->getAllStudentRecords())
            ->filter(fn (array $record): bool => (string) ($record['is_active'] ?? '') === '1')
            ->values()
            ->all();
    }

    /**
     * Distinct, non-empty batch values present on the cached roster.
     *
     * @return array<int,string>
     */
    public function availableBatches(): array
    {
        return collect($this->getAllStudentRecords())
            ->map(fn (array $record): string => trim((string) ($record['batch'] ?? '')))
            ->filter(fn (string $batch): bool => $batch !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Build a datatelid → student record map from the cached roster.
     *
     * @return array<string,array<string,mixed>>
     */
    public function studentMapByDatatelId(): array
    {
        $map = [];
        foreach ($this->getAllStudentRecords() as $record) {
            $datatelId = (string) ($record['datatelid'] ?? '');
            if ($datatelId !== '') {
                $map[$datatelId] = $record;
            }
        }

        return $map;
    }
}
