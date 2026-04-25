<?php

namespace App\Services;

use App\Models\Redcap_lib;
use App\Support\FinalScoreFormulaParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Wraps Redcap_lib for the destination student list project (REDCAP_TOKEN).
 */
class RedcapDestinationService
{
    private string $token;

    private string $url;

    private ?array $finalScoreFormulas = null;

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
        $cacheKey = 'student:datatelid:'.$datatelId;

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
     * Fetch and parse destination REDCap calculated-field formulas for final scores.
     *
     * @return array<string,array{field:string,formula:string,components:array<int,array{field:string,label:string,coefficient:float,max_value:float,max_points:float,weight_percent:float}>}>
     */
    public function finalScoreFormulas(int $cacheMinutes = 0): array
    {
        if ($this->finalScoreFormulas !== null) {
            return $this->finalScoreFormulas;
        }

        $this->finalScoreFormulas = $cacheMinutes > 0
            ? Cache::remember('destination:final_score_formulas', now()->addMinutes($cacheMinutes), fn (): array => $this->fetchFinalScoreFormulas())
            : $this->fetchFinalScoreFormulas();

        return $this->finalScoreFormulas;
    }

    /**
     * @return array<string,array{field:string,formula:string,components:array<int,array{field:string,label:string,coefficient:float,max_value:float,max_points:float,weight_percent:float}>}>
     */
    private function fetchFinalScoreFormulas(): array
    {
        try {
            $metadata = Redcap_lib::exportMetadata(
                format: 'json',
                returnAs: 'array',
                url: $this->url,
                token: $this->token,
            );
        } catch (\Throwable $e) {
            Log::warning('Unable to fetch destination final score formulas from REDCap metadata.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        return is_array($metadata) ? FinalScoreFormulaParser::fromMetadata($metadata) : [];
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
