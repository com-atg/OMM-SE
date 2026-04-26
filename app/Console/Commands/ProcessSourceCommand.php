<?php

namespace App\Console\Commands;

use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('omm:process-source
    {--pid= : Process a specific PID (resolved from project_mappings) instead of the current academic year}
    {--dry-run : Aggregate without writing back to the destination project}')]
#[Description('Aggregate all source evaluation records and push results to the destination REDCap project.')]
class ProcessSourceCommand extends Command
{
    public function handle(
        RedcapSourceService $source,
        RedcapDestinationService $destination,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $pid = $this->option('pid');

        // Resolve token from project_mappings.
        if ($pid !== null) {
            $mapping = ProjectMapping::query()->where('redcap_pid', $pid)->first();
            if (! $mapping) {
                $this->error("No project mapping found for PID {$pid}.");

                return self::FAILURE;
            }
            $label = "PID {$pid}";
        } else {
            $mapping = ProjectMapping::latestSourceProject();
            if (! $mapping) {
                $this->error('No project mapping configured. Add one in Settings before running.');

                return self::FAILURE;
            }
            $label = $mapping->displayName().' / PID '.$mapping->redcap_pid;
        }

        $token = (string) $mapping->redcap_token;

        $this->info("Processing {$label}".($dryRun ? ' <comment>[dry-run — no writes]</comment>' : '').'…');

        // ── Fetch ─────────────────────────────────────────────────────────────

        $this->line('  Fetching records from source…');

        try {
            $records = $source->fetchAllRecords($token);
        } catch (\Throwable $e) {
            $this->error("Failed to fetch source records: {$e->getMessage()}");
            Log::error('omm:process-source fetch failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $total = count($records);
        $this->line("  <info>{$total}</info> record(s) fetched.");

        if ($total === 0) {
            $this->warn('No source records found. Nothing to process.');

            return self::SUCCESS;
        }

        // ── Group by student + semester ───────────────────────────────────────

        $groups = [];
        $skipped = ['missing_required_fields' => 0];

        foreach ($records as $record) {
            $student = trim((string) ($record['student'] ?? ''));
            $semester = trim((string) ($record['semester'] ?? ''));

            if ($student === '' || $semester === '') {
                $skipped['missing_required_fields']++;

                continue;
            }

            $groups["{$student}|{$semester}"][] = $record;
        }

        $groupCount = count($groups);
        $this->line("  <info>{$groupCount}</info> student-semester group(s) identified.");

        // ── Load destination student map ──────────────────────────────────────

        $this->line('  Loading destination student map…');

        try {
            $studentMap = $destination->studentMapByDatatelId();
        } catch (\Throwable $e) {
            $this->error("Failed to fetch destination student map: {$e->getMessage()}");
            Log::error('omm:process-source student map failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        // ── Process each group ────────────────────────────────────────────────

        $this->newLine();
        $bar = $this->output->createProgressBar($groupCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
        $bar->setMessage('starting…');
        $bar->start();

        $updated = 0;
        $failed = 0;
        $notFound = 0;
        $unknownSemester = 0;

        foreach ($groups as $key => $groupEvals) {
            [$datatelId, $semesterCode] = explode('|', $key);
            $semester = EvalAggregator::SEMESTER_MAP[$semesterCode] ?? null;

            if ($semester === null) {
                $unknownSemester++;
                $bar->setMessage("skipped unknown semester '{$semesterCode}'");
                $bar->advance();

                continue;
            }

            $studentRecord = $studentMap[$datatelId] ?? null;

            if (! $studentRecord) {
                $notFound++;
                $bar->setMessage("student {$datatelId} not found in destination");
                $bar->advance();

                continue;
            }

            $aggregates = EvalAggregator::aggregate($groupEvals, $semester);

            $bar->setMessage("{$studentRecord['record_id']} · {$semester} · {$aggregates['by_category']['teaching']['nu']}T/{$aggregates['by_category']['clinic']['nu']}C/{$aggregates['by_category']['research']['nu']}R/{$aggregates['by_category']['didactics']['nu']}D evals");

            if (! $dryRun) {
                try {
                    $destination->updateStudentRecord(array_merge(
                        ['record_id' => $studentRecord['record_id']],
                        $aggregates['fields'],
                    ));
                    $updated++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('omm:process-source update failed', [
                        'datatelid' => $datatelId,
                        'semester' => $semester,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $updated++;
            }

            $bar->advance();
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine(2);

        // ── Invalidate caches ─────────────────────────────────────────────────

        if (! $dryRun) {
            Cache::forget('dashboard:stats');
            Cache::forget('destination:all_students');
        }

        // ── Summary ───────────────────────────────────────────────────────────

        $this->table(
            ['Metric', 'Count'],
            [
                ['Source records', $total],
                ['Student-semester groups', $groupCount],
                [$dryRun ? 'Would update' : 'Updated', $updated],
                ['Failed', $failed],
                ['Student not found', $notFound],
                ['Unknown semester', $unknownSemester],
                ['Missing required fields', $skipped['missing_required_fields']],
            ],
        );

        if ($failed > 0) {
            $this->warn("{$failed} update(s) failed — check logs for details.");

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run complete. No changes were written.' : 'Processing complete.');

        return self::SUCCESS;
    }
}
