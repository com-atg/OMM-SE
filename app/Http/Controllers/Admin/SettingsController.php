<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Mail\EvaluationNotification;
use App\Models\AppSetting;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\MailTemplateRenderer;
use App\Services\RedcapDestinationService;
use App\Support\SemesterSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(): View
    {
        $projectMappings = ProjectMapping::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();
        $trashedProjectMappings = ProjectMapping::onlyTrashed()
            ->orderByDesc('id')
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
            'currentProject' => ProjectMapping::activeSource(),
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

    public function create(): View
    {
        return view('admin.settings.new-source-project');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedProjectMapping($request);
        $validated['is_active'] = true;

        DB::transaction(function () use ($validated, &$projectMapping): void {
            ProjectMapping::query()->where('is_active', true)->update(['is_active' => false]);
            $projectMapping = ProjectMapping::create($validated);
        });

        return redirect()
            ->route('admin.settings.project-mappings.import-students', $projectMapping)
            ->with('status', 'Source project created and marked active. Importing scholars from REDCap...');
    }

    public function importStudents(ProjectMapping $projectMapping, RedcapDestinationService $destination): View
    {
        Cache::forget('destination:all_students');
        $records = $destination->getAllStudentRecords();

        $created = [];
        $updated = [];
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

            $cohortTerm = $this->cohortTermFromRecord($record);
            $cohortYear = $this->cohortYearFromRecord($record);
            $batch = $this->batchFromRecord($record);
            $isActive = $this->isActiveFromRecord($record);
            $recordId = (string) ($record['record_id'] ?? '') ?: null;
            $finalName = $name !== '' ? $name : $email;

            $existing = User::withTrashed()->where('email', $email)->first();

            if ($existing !== null) {
                $existing->fill([
                    'name' => $finalName,
                    'redcap_record_id' => $recordId,
                    'cohort_start_term' => $cohortTerm,
                    'cohort_start_year' => $cohortYear,
                    'batch' => $batch,
                    'is_active' => $isActive,
                ])->save();

                $updated[] = ['email' => $email, 'name' => $finalName];

                continue;
            }

            User::create([
                'email' => $email,
                'name' => $finalName,
                'role' => Role::Student,
                'redcap_record_id' => $recordId,
                'cohort_start_term' => $cohortTerm,
                'cohort_start_year' => $cohortYear,
                'batch' => $batch,
                'is_active' => $isActive,
            ]);

            $created[] = ['email' => $email, 'name' => $finalName];
        }

        return view('admin.settings.import-students-result', [
            'projectMapping' => $projectMapping,
            'totalFetched' => count($records),
            'created' => $created,
            'updated' => $updated,
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
            ->with('status', 'Source project updated.');
    }

    public function destroy(ProjectMapping $projectMapping): RedirectResponse
    {
        $projectMapping->delete();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Source project mapping deleted.');
    }

    public function restore(int $id): RedirectResponse
    {
        $projectMapping = ProjectMapping::onlyTrashed()->findOrFail($id);
        $projectMapping->restore();

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Source project mapping restored.');
    }

    public function activate(ProjectMapping $projectMapping): RedirectResponse
    {
        DB::transaction(function () use ($projectMapping): void {
            ProjectMapping::query()->where('is_active', true)->update(['is_active' => false]);
            $projectMapping->update(['is_active' => true]);
        });

        return redirect()
            ->route('admin.settings.index')
            ->with('status', "PID {$projectMapping->redcap_pid} is now the active source project.");
    }

    /**
     * @return array{redcap_pid: int, redcap_token?: string}
     */
    private function validatedProjectMapping(Request $request, ?ProjectMapping $projectMapping = null): array
    {
        $projectMappingId = $projectMapping?->id;
        $tokenRules = $projectMapping === null
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];

        return $request->validate([
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

    private function cohortTermFromRecord(array $record): ?string
    {
        $term = trim((string) ($record['cohort_start_term'] ?? ''));

        return in_array($term, SemesterSlot::TERMS, true) ? $term : null;
    }

    private function cohortYearFromRecord(array $record): ?int
    {
        $year = trim((string) ($record['cohort_start_year'] ?? ''));

        return ctype_digit($year) ? (int) $year : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function batchFromRecord(array $record): ?string
    {
        $batch = trim((string) ($record['batch'] ?? ''));

        return $batch !== '' ? $batch : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function isActiveFromRecord(array $record): bool
    {
        $raw = trim((string) ($record['is_active'] ?? '1'));

        return $raw !== '0';
    }
}
