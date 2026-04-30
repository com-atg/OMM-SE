<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Support\SemesterSlot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportScholarsJob implements ShouldQueue
{
    use Queueable;

    public const CACHE_PREFIX = 'import_scholars:';

    public const TTL_MINUTES = 60;

    /** Flush per-record progress to the cache no more often than this. */
    private const PROGRESS_FLUSH_EVERY = 5;

    public function __construct(
        public string $jobId,
        public int $projectMappingId,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->projectMappingId))
                ->dontRelease()
                ->expireAfter(self::TTL_MINUTES * 60),
        ];
    }

    public function handle(RedcapDestinationService $destination): void
    {
        $cacheKey = self::cacheKey($this->jobId);
        $state = Cache::get($cacheKey, []);

        try {
            $projectMapping = ProjectMapping::findOrFail($this->projectMappingId);

            Cache::forget('destination:all_students');
            $records = $destination->getAllStudentRecords();

            $state['status'] = 'running';
            $state['total_fetched'] = count($records);
            $state['processed'] = 0;
            $state['created'] = [];
            $state['updated'] = [];
            $state['missing_email'] = [];
            $state['failed'] = [];
            $this->putState($cacheKey, $state);

            foreach ($records as $i => $record) {
                $this->processRecord($record, $state);
                $state['processed']++;

                if (($i + 1) % self::PROGRESS_FLUSH_EVERY === 0) {
                    $this->putState($cacheKey, $state);
                }
            }

            $state['status'] = 'complete';
            $state['finished_at'] = now()->toIso8601String();
            $this->putState($cacheKey, $state);
        } catch (\Throwable $e) {
            Log::error('ImportScholarsJob: fatal error', [
                'project_mapping_id' => $this->projectMappingId,
                'error' => $e->getMessage(),
            ]);
            $state['status'] = 'failed';
            $state['error'] = $e->getMessage();
            $state['finished_at'] = now()->toIso8601String();
            $this->putState($cacheKey, $state);
        }
    }

    public function failed(\Throwable $e): void
    {
        $cacheKey = self::cacheKey($this->jobId);
        $state = Cache::get($cacheKey, []);
        $state['status'] = 'failed';
        $state['error'] = $e->getMessage();
        $state['finished_at'] = now()->toIso8601String();
        $this->putState($cacheKey, $state);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $state
     */
    private function processRecord(array $record, array &$state): void
    {
        $email = strtolower(trim((string) ($record['email'] ?? '')));
        $firstName = trim((string) ($record['goes_by'] ?? '')) !== ''
            ? trim((string) $record['goes_by'])
            : trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));
        $name = trim("{$firstName} {$lastName}");
        $recordId = (string) ($record['record_id'] ?? '');

        if ($email === '') {
            $state['missing_email'][] = [
                'record_id' => $recordId,
                'name' => $name !== '' ? $name : '(unknown)',
            ];

            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $state['failed'][] = [
                'record_id' => $recordId,
                'name' => $name ?: $email,
                'reason' => 'Invalid email format',
            ];

            return;
        }

        try {
            $cohortTerm = $this->cohortTerm($record);
            $cohortYear = $this->cohortYear($record);
            $batch = $this->batch($record);
            $isActive = $this->isActive($record);
            $finalName = $name !== '' ? $name : $email;

            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing !== null) {
                $existing->fill([
                    'name' => $finalName,
                    'redcap_record_id' => $recordId !== '' ? $recordId : null,
                    'cohort_start_term' => $cohortTerm,
                    'cohort_start_year' => $cohortYear,
                    'batch' => $batch,
                    'is_active' => $isActive,
                ])->save();

                $state['updated'][] = ['email' => $email, 'name' => $finalName];

                return;
            }

            User::create([
                'email' => $email,
                'name' => $finalName,
                'role' => Role::Student,
                'redcap_record_id' => $recordId !== '' ? $recordId : null,
                'cohort_start_term' => $cohortTerm,
                'cohort_start_year' => $cohortYear,
                'batch' => $batch,
                'is_active' => $isActive,
            ]);
            $state['created'][] = ['email' => $email, 'name' => $finalName];
        } catch (\Throwable $e) {
            Log::warning('ImportScholarsJob: failed to create user', [
                'email' => $email,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
            $state['failed'][] = [
                'record_id' => $recordId,
                'name' => $name ?: $email,
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function putState(string $cacheKey, array $state): void
    {
        Cache::put($cacheKey, $state, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function cacheKey(string $jobId): string
    {
        return self::CACHE_PREFIX.$jobId;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function cohortTerm(array $record): ?string
    {
        $term = trim((string) ($record['cohort_start_term'] ?? ''));

        return in_array($term, SemesterSlot::TERMS, true) ? $term : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function cohortYear(array $record): ?int
    {
        $year = trim((string) ($record['cohort_start_year'] ?? ''));

        return ctype_digit($year) ? (int) $year : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function batch(array $record): ?string
    {
        $batch = trim((string) ($record['batch'] ?? ''));

        return $batch !== '' ? $batch : null;
    }

    /**
     * REDCap returns checkbox/yesno values as the strings "1"/"0" or empty.
     * Treat anything other than an explicit "0" as active so a missing field
     * defaults to true rather than silently deactivating the user.
     *
     * @param  array<string, mixed>  $record
     */
    private function isActive(array $record): bool
    {
        $raw = trim((string) ($record['is_active'] ?? '1'));

        return $raw !== '0';
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(string $jobId, int $projectMappingId): array
    {
        return [
            'job_id' => $jobId,
            'project_mapping_id' => $projectMappingId,
            'status' => 'pending',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'total_fetched' => 0,
            'processed' => 0,
            'created' => [],
            'updated' => [],
            'missing_email' => [],
            'failed' => [],
            'error' => null,
        ];
    }
}
