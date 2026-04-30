<?php

namespace App\Jobs;

use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use App\Support\SemesterSlot;
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
        public int $projectMappingId,
        public bool $activeOnly = false,
        public ?string $batch = null,
    ) {}

    public function handle(
        RedcapDestinationService $destination,
        RedcapSourceService $source,
    ): void {
        $cacheKey = self::cacheKey($this->jobId);
        $state = Cache::get($cacheKey, []);

        try {
            $mapping = ProjectMapping::find($this->projectMappingId);

            if ($mapping === null) {
                throw new \RuntimeException("Project mapping {$this->projectMappingId} not found.");
            }

            $records = $source->fetchAllRecords((string) $mapping->redcap_token);

            $studentMap = $destination->studentMapByDatatelId();

            if ($this->activeOnly || $this->batch !== null) {
                $studentMap = array_filter($studentMap, function (array $record): bool {
                    if ($this->activeOnly && (string) ($record['is_active'] ?? '') !== '1') {
                        return false;
                    }

                    if ($this->batch !== null && trim((string) ($record['batch'] ?? '')) !== $this->batch) {
                        return false;
                    }

                    return true;
                });
            }

            $groups = $this->groupByStudentSlot($records, $studentMap, $state);

            $state['status'] = 'running';
            $state['total_records'] = count($records);
            $state['total_groups'] = count($groups);
            $state['processed_groups'] = 0;
            $state['updated'] = 0;
            $state['unchanged'] = 0;
            $state['failed'] = 0;
            Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));

            foreach ($groups as $key => $groupEvals) {
                [$datatelId, $slotKey] = explode('|', $key);

                $studentRecord = $studentMap[$datatelId] ?? null;

                if (! $studentRecord) {
                    $this->bumpSkip($state, 'student_not_found');
                    $this->tick($cacheKey, $state);

                    continue;
                }

                $aggregates = EvalAggregator::aggregate($groupEvals, $slotKey);

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
                        'destination_record_id' => $studentRecord['record_id'] ?? null,
                        'slot' => $slotKey,
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
            $state['error'] = 'Processing failed. Check application logs for details.';
            $state['finished_at'] = now()->toIso8601String();
            Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));
        }
    }

    public static function cacheKey(string $jobId): string
    {
        return self::CACHE_PREFIX.$jobId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(string $jobId, string $pid, bool $activeOnly = false, ?string $batch = null): array
    {
        return [
            'job_id' => $jobId,
            'pid' => $pid,
            'status' => 'pending',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'total_records' => 0,
            'total_groups' => 0,
            'processed_groups' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'skip_reasons' => [],
            'error' => null,
            'filter_active_only' => $activeOnly,
            'filter_batch' => $batch,
        ];
    }

    /**
     * Group source records by "{student}|{slotKey}". Records missing required
     * fields, with no matching scholar in the destination, or with no resolvable
     * cohort window are tallied as skips and excluded from groups.
     *
     * @param  array<int,array<string,mixed>>  $records
     * @param  array<string,array<string,mixed>>  $studentMap
     * @param  array<string,mixed>  $state
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function groupByStudentSlot(array $records, array $studentMap, array &$state): array
    {
        $groups = [];

        foreach ($records as $record) {
            $student = (string) ($record['student'] ?? '');
            $semester = (string) ($record['semester'] ?? '');
            $category = (string) ($record['eval_category'] ?? '');
            $dateLab = (string) ($record['date_lab'] ?? '');

            if ($student === '' || $semester === '' || $category === '' || $dateLab === '') {
                $this->bumpSkip($state, 'missing_required_fields');

                continue;
            }

            $studentRecord = $studentMap[$student] ?? null;

            if (! $studentRecord) {
                $this->bumpSkip($state, 'student_not_found');

                continue;
            }

            $cohortTerm = trim((string) ($studentRecord['cohort_start_term'] ?? '')) ?: null;
            $cohortYearRaw = trim((string) ($studentRecord['cohort_start_year'] ?? ''));
            $cohortYear = $cohortYearRaw !== '' && ctype_digit($cohortYearRaw) ? (int) $cohortYearRaw : null;

            $slot = SemesterSlot::compute($semester, $dateLab, $cohortTerm, $cohortYear);

            if ($slot === null) {
                $this->bumpSkip($state, 'out_of_cohort_window');

                continue;
            }

            $slotKey = SemesterSlot::slotKey($slot);
            $groups["{$student}|{$slotKey}"][] = $record;
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
