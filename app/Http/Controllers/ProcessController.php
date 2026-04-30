<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSourceProjectJob;
use App\Models\ProjectMapping;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProcessController extends Controller
{
    /**
     * Kick off bulk aggregation for a source REDCap project identified by PID.
     * Token is resolved from the project_mappings table.
     */
    public function show(string $pid): View|Response
    {
        if (! preg_match('/^\d+$/', $pid)) {
            abort(404, 'Invalid PID.');
        }

        $mapping = ProjectMapping::query()
            ->where('redcap_pid', $pid)
            ->first();

        if (! $mapping) {
            abort(404, "No project mapping found for PID {$pid}.");
        }

        $jobId = (string) Str::uuid();

        Cache::put(ProcessSourceProjectJob::cacheKey($jobId), [
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
        ], now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES));

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, $pid, $mapping->id);

        return view('process', [
            'pid' => $pid,
            'jobId' => $jobId,
            'active' => 'dashboard',
        ]);
    }

    /**
     * Kick off bulk aggregation for the current academic year's source project,
     * resolved from project_mappings (latest by graduation/academic year).
     * This is the one-click "process all evaluations" action available from the dashboard.
     */
    public function run(): View|Response
    {
        $mapping = ProjectMapping::latestSourceProject();

        abort_if(
            $mapping === null,
            503,
            'No project mapping configured. Add one in Settings before running.'
        );

        $pid = (string) $mapping->redcap_pid;

        $jobId = (string) Str::uuid();

        Cache::put(ProcessSourceProjectJob::cacheKey($jobId), [
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
        ], now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES));

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, $pid, $mapping->id);

        return view('process', [
            'pid' => $mapping->displayName().' / PID '.$pid,
            'jobId' => $jobId,
            'active' => 'dashboard',
        ]);
    }

    public function status(string $jobId): JsonResponse
    {
        $state = Cache::get(ProcessSourceProjectJob::cacheKey($jobId));

        if (! $state) {
            return response()->json(['status' => 'unknown'], 404);
        }

        return response()->json($state);
    }
}
