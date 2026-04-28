<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceProjectJob;
use App\Mail\EvaluationNotification;
use App\Models\AppSetting;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\MailTemplateRenderer;
use App\Services\RedcapDestinationService;
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

        $emailTemplateSetting = null;
        $emailPreviewHtml = '';

        if (auth()->user()->can('edit-email-template')) {
            $emailTemplateSetting = AppSetting::where('key', 'email_template')->first();
            $template = AppSetting::get('email_template')
                ?? file_get_contents(resource_path('views/emails/evaluation.blade.php'));
            $emailPreviewHtml = $this->renderEmailPreview($template);
        }

        return view('admin.settings.index', [
            'currentProject' => ProjectMapping::current(),
            'projectMappings' => $projectMappings,
            'trashedProjectMappings' => $trashedProjectMappings,
            'emailTemplateSetting' => $emailTemplateSetting,
            'emailPreviewHtml' => $emailPreviewHtml,
        ]);
    }

    private function renderEmailPreview(string $template): string
    {
        try {
            return app(MailTemplateRenderer::class)->render(
                $template,
                EvaluationNotification::sampleViewData(),
            );
        } catch (\Throwable) {
            return '';
        }
    }

    public function newAcademicYear(): View
    {
        $latestGraduationYear = (int) ProjectMapping::query()->max('graduation_year');
        $nextGraduationYear = $latestGraduationYear > 0
            ? $latestGraduationYear + 1
            : 2028;

        return view('admin.settings.new-academic-year', [
            'nextGraduationYear' => $nextGraduationYear,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $projectMapping = ProjectMapping::create($this->validatedProjectMapping($request));

        return redirect()
            ->route('admin.settings.project-mappings.import-students', $projectMapping)
            ->with('status', 'Project mapping created. Importing scholars from REDCap...');
    }

    public function importStudents(ProjectMapping $projectMapping, RedcapDestinationService $destination): View
    {
        Cache::forget('destination:all_students');
        $records = $destination->getStudentsByGraduationYear($projectMapping->graduation_year);

        $created = [];
        $skipped = [];
        $missingEmail = [];

        foreach ($records as $record) {
            $email = strtolower(trim((string) ($record['email'] ?? '')));
            $firstName = trim((string) ($record['goes_by'] ?? '')) !== ''
                ? trim((string) $record['goes_by'])
                : trim((string) ($record['first_name'] ?? ''));
            $lastName = trim((string) ($record['last_name'] ?? ''));
            $name = trim("{$firstName} {$lastName}");

            if ($email === '') {
                $missingEmail[] = [
                    'record_id' => (string) ($record['record_id'] ?? ''),
                    'name' => $name !== '' ? $name : '(unknown)',
                ];

                continue;
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                $skipped[] = ['email' => $email, 'name' => $name ?: $email];

                continue;
            }

            User::create([
                'email' => $email,
                'name' => $name !== '' ? $name : $email,
                'role' => Role::Student,
                'redcap_record_id' => (string) ($record['record_id'] ?? '') ?: null,
            ]);

            $created[] = ['email' => $email, 'name' => $name ?: $email];
        }

        return view('admin.settings.import-students-result', [
            'projectMapping' => $projectMapping,
            'totalFetched' => count($records),
            'created' => $created,
            'skipped' => $skipped,
            'missingEmail' => $missingEmail,
        ]);
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
            'unchanged' => 0,
            'failed' => 0,
            'skip_reasons' => [],
            'error' => null,
        ], now()->addMinutes(ProcessSourceProjectJob::TTL_MINUTES));

        ProcessSourceProjectJob::dispatchAfterResponse($jobId, $pid, $projectMapping->redcap_token);

        return view('process', [
            'pid' => "{$projectMapping->displayName()} / PID {$pid}",
            'jobId' => $jobId,
            'active' => 'settings',
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
