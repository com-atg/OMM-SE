<?php

namespace App\Jobs;

use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessSourceProjectJob implements ShouldQueue
{
    use Queueable;

    public const CACHE_PREFIX = 'process:';

    public const TTL_MINUTES = 60;

    public function __construct(
        public string $jobId,
        public string $pid,
        public string $sourceToken,
    ) {}

    public function handle(
        RedcapDestinationService $destination,
        RedcapSourceService $source,
    ): void {
        $cacheKey = self::cacheKey($this->jobId);
        $state = Cache::get($cacheKey, []);

        try {
            $records = $source->fetchAllRecords($this->sourceToken);

            $groups = $this->groupByStudentSemester($records, $state);

            $studentMap = $destination->studentMapByDatatelId();

            $state['status'] = 'running';
            $state['total_records'] = count($records);
            $state['total_groups'] = count($groups);
            $state['processed_groups'] = 0;
            $state['updated'] = 0;
            $state['unchanged'] = 0;
            $state['failed'] = 0;
            Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));

            foreach ($groups as $key => $groupEvals) {
                [$datatelId, $semesterCode] = explode('|', $key);
                $semester = EvalAggregator::SEMESTER_MAP[$semesterCode] ?? null;

                if ($semester === null) {
                    $this->bumpSkip($state, 'unknown_semester');
                    $this->tick($cacheKey, $state);

                    continue;
                }

                $studentRecord = $studentMap[$datatelId] ?? null;

                if (! $studentRecord) {
                    $this->bumpSkip($state, 'student_not_found');
                    $this->tick($cacheKey, $state);

                    continue;
                }

                $aggregates = EvalAggregator::aggregate($groupEvals, $semester);

                $payload = array_merge(
                    ['record_id' => $studentRecord['record_id']],
                    $aggregates['fields'],
                );

                if ($this->recordAlreadyHasValues($studentRecord, $payload)) {
                    $state['unchanged']++;
                    $this->tick($cacheKey, $state);

                    continue;
                }

                try {
                    $destination->updateStudentRecord($payload);
                    $state['updated']++;
                } catch (\Throwable $e) {
                    Log::error('ProcessSourceProjectJob: update failed', [
                        'datatelid' => $datatelId,
                        'semester' => $semester,
                        'error' => $e->getMessage(),
                    ]);
                    $state['failed']++;
                }

                $this->tick($cacheKey, $state);
            }

            $state['status'] = 'complete';
            $state['finished_at'] = now()->toIso8601String();
            Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));

            // Invalidate dashboard cache so fresh data appears.
            Cache::forget('dashboard:stats');
            Cache::forget('destination:all_students');
        } catch (\Throwable $e) {
            Log::error('ProcessSourceProjectJob: fatal error', [
                'pid' => $this->pid,
                'error' => $e->getMessage(),
            ]);
            $state['status'] = 'failed';
            $state['error'] = $e->getMessage();
            $state['finished_at'] = now()->toIso8601String();
            Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));
        }
    }

    public static function cacheKey(string $jobId): string
    {
        return self::CACHE_PREFIX.$jobId;
    }

    /**
     * Group source records by "{student}|{semester}". Records missing required
     * fields are tallied as skips in the state and excluded from groups.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @param  array<string,mixed>  $state
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function groupByStudentSemester(array $records, array &$state): array
    {
        $groups = [];

        foreach ($records as $record) {
            $student = (string) ($record['student'] ?? '');
            $semester = (string) ($record['semester'] ?? '');
            $category = (string) ($record['eval_category'] ?? '');

            if ($student === '' || $semester === '' || $category === '') {
                $this->bumpSkip($state, 'missing_required_fields');

                continue;
            }

            $groups["{$student}|{$semester}"][] = $record;
        }

        return $groups;
    }

    /**
     * @param  array<string,mixed>  $state
     */
    private function bumpSkip(array &$state, string $reason): void
    {
        $state['skip_reasons'][$reason] = ($state['skip_reasons'][$reason] ?? 0) + 1;
    }

    /**
     * @param  array<string,mixed>  $state
     */
    private function tick(string $cacheKey, array &$state): void
    {
        $state['processed_groups'] = ($state['processed_groups'] ?? 0) + 1;
        Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * @param  array<string,mixed>  $record
     * @param  array<string,mixed>  $payload
     */
    private function recordAlreadyHasValues(array $record, array $payload): bool
    {
        foreach ($payload as $field => $value) {
            if ($field === 'record_id') {
                continue;
            }

            if (! array_key_exists($field, $record) || ! $this->valuesMatch($record[$field], $value)) {
                return false;
            }
        }

        return true;
    }

    private function valuesMatch(mixed $existing, mixed $incoming): bool
    {
        if (is_numeric($existing) && is_numeric($incoming)) {
            return (float) $existing === (float) $incoming;
        }

        return trim((string) $existing) === trim((string) $incoming);
    }
}
