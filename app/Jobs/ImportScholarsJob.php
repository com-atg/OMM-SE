<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
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
            $records = $destination->getStudentsByGraduationYear($projectMapping->graduation_year);

            $state['status'] = 'running';
            $state['total_fetched'] = count($records);
            $state['processed'] = 0;
            $state['created'] = [];
            $state['skipped'] = [];
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

        if (User::withTrashed()->where('email', $email)->exists()) {
            $state['skipped'][] = ['email' => $email, 'name' => $name ?: $email];

            return;
        }

        try {
            User::create([
                'email' => $email,
                'name' => $name !== '' ? $name : $email,
                'role' => Role::Student,
                'redcap_record_id' => $recordId !== '' ? $recordId : null,
            ]);
            $state['created'][] = ['email' => $email, 'name' => $name ?: $email];
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
            'skipped' => [],
            'missing_email' => [],
            'failed' => [],
            'error' => null,
        ];
    }
}
