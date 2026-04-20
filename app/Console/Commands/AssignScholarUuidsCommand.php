<?php

namespace App\Console\Commands;

use App\Services\RedcapDestinationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Signature('scholars:assign-uuids {--dry-run : Report without writing changes} {--force : Overwrite existing UUID values}')]
#[Description('Assign a UUID to each destination scholar record (skips records that already have one unless --force is passed).')]
class AssignScholarUuidsCommand extends Command
{
    public function handle(RedcapDestinationService $destination): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $records = $destination->getAllScholarRecords();

        $total = count($records);
        if ($total === 0) {
            $this->warn('No scholar records returned from the destination project.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '<fg=cyan>Found %d scholar record%s.</> Mode: <fg=yellow>%s</>',
            $total,
            $total === 1 ? '' : 's',
            $dryRun ? 'dry-run' : ($force ? 'overwrite existing' : 'fill missing only'),
        ));

        $assigned = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $record) {
            $recordId = (string) ($record['record_id'] ?? '');
            if ($recordId === '') {
                $skipped++;
                $bar->advance();

                continue;
            }

            $existingUuid = trim((string) ($record['uuid'] ?? ''));
            if ($existingUuid !== '' && ! $force) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $newUuid = (string) Str::uuid();

            if ($dryRun) {
                $assigned++;
                $bar->advance();

                continue;
            }

            try {
                $destination->updateScholarRecord([
                    'record_id' => $recordId,
                    'uuid' => $newUuid,
                ]);
                $assigned++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Failed to update record {$recordId}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Total', 'Assigned'.($dryRun ? ' (dry)' : ''), 'Skipped (already had UUID)', 'Failed'],
            [[$total, $assigned, $skipped, $failed]],
        );

        if (! $dryRun && $assigned > 0) {
            Cache::forget('destination:all_scholars');
            Cache::forget('dashboard:stats');
            $this->info('Caches invalidated. Dashboard will refresh on next request.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
