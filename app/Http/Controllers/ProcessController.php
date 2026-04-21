<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSourceProjectJob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProcessController extends Controller
{
    /**
     * Kick off bulk aggregation for a source REDCap project identified by PID.
     * Token is resolved from env var REDCAP_TOKEN_PID_{pid}.
     */
    public function show(string $pid): View|Response
    {
        if (! preg_match('/^\d+$/', $pid)) {
            abort(404, 'Invalid PID.');
        }

        $token = env("REDCAP_TOKEN_PID_{$pid}");

        if (! $token) {
            abort(404, "No REDCap token configured for PID {$pid}. Add REDCAP_TOKEN_PID_{$pid} to .env.");
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
            'failed' => 0,
            'skip_reasons' => [],
            'error' => null,
        ], now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES));

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, $pid, $token);

        return view('process', [
            'pid' => $pid,
            'jobId' => $jobId,
        ]);
    }

    /**
     * Kick off bulk aggregation for the configured source project (REDCAP_SOURCE_TOKEN).
     * This is the one-click "process all evaluations" action available from the dashboard.
     */
    public function run(): View|Response
    {
        $token = config('redcap.source_token');

        abort_if(
            empty($token),
            503,
            'REDCAP_SOURCE_TOKEN is not configured. Add it to .env before running.'
        );

        $jobId = (string) Str::uuid();

        Cache::put(ProcessSourceProjectJob::cacheKey($jobId), [
            'job_id' => $jobId,
            'pid' => 'source',
            'status' => 'pending',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'total_records' => 0,
            'total_groups' => 0,
            'processed_groups' => 0,
            'updated' => 0,
            'failed' => 0,
            'skip_reasons' => [],
            'error' => null,
        ], now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES));

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, 'source', $token);

        return view('process', [
            'pid' => 'Source Project',
            'jobId' => $jobId,
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
