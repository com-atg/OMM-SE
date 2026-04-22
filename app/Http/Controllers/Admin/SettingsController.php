<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceProjectJob;
use App\Models\ProjectMapping;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    public function index(): View
    {
        $projectMappings = ProjectMapping::query()
            ->orderByDesc('graduation_year')
            ->orderByDesc('academic_year')
            ->get();
        $trashedProjectMappings = ProjectMapping::onlyTrashed()
            ->orderByDesc('graduation_year')
            ->get();

        return view('admin.settings.index', [
            'currentProject' => ProjectMapping::current(),
            'projectMappings' => $projectMappings,
            'trashedProjectMappings' => $trashedProjectMappings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProjectMapping::create($this->validatedProjectMapping($request));

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Project mapping created.');
    }

    public function edit(ProjectMapping $projectMapping): View
    {
        return view('admin.settings.edit', [
            'projectMapping' => $projectMapping,
        ]);
    }

    public function update(Request $request, ProjectMapping $projectMapping): RedirectResponse
    {
        $validated = $this->validatedProjectMapping($request, $projectMapping);

        if (($validated['redcap_token'] ?? '') === '') {
            unset($validated['redcap_token']);
        }

        $projectMapping->update($validated);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Project mapping updated.');
    }

    public function destroy(ProjectMapping $projectMapping): RedirectResponse
    {
        $projectMapping->delete();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Project mapping deleted.');
    }

    public function restore(int $id): RedirectResponse
    {
        $projectMapping = ProjectMapping::onlyTrashed()->findOrFail($id);
        $projectMapping->restore();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Project mapping restored.');
    }

    public function process(ProjectMapping $projectMapping): View|Response
    {
        $jobId = (string) Str::uuid();
        $pid = (string) $projectMapping->redcap_pid;

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

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, $pid, $projectMapping->redcap_token);

        return view('process', [
            'pid' => "{$projectMapping->displayName()} / PID {$pid}",
            'jobId' => $jobId,
        ]);
    }

    /**
     * @return array{academic_year: string, graduation_year: int, redcap_pid: int, redcap_token?: string}
     */
    private function validatedProjectMapping(Request $request, ?ProjectMapping $projectMapping = null): array
    {
        $projectMappingId = $projectMapping?->id;
        $tokenRules = $projectMapping === null
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];

        return $request->validate([
            'academic_year' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{4}$/',
                'max:9',
                Rule::unique('project_mappings', 'academic_year')
                    ->whereNull('deleted_at')
                    ->ignore($projectMappingId),
            ],
            'graduation_year' => [
                'required',
                'integer',
                'between:2000,2100',
                Rule::unique('project_mappings', 'graduation_year')
                    ->whereNull('deleted_at')
                    ->ignore($projectMappingId),
            ],
            'redcap_pid' => [
                'required',
                'integer',
                'between:1,4294967295',
                Rule::unique('project_mappings', 'redcap_pid')
                    ->whereNull('deleted_at')
                    ->ignore($projectMappingId),
            ],
            'redcap_token' => $tokenRules,
        ]);
    }
}
