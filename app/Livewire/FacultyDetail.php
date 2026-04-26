<?php

namespace App\Livewire;

use App\Mail\EvaluationNotification;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

class FacultyDetail extends Component
{
    public string $selectedFaculty = '';

    public ?string $selectedRecordId = null;

    public bool $detailModalOpen = false;

    public ?int $selectedGraduationYear = null;

    public const SESSION_KEY = 'academic_year_filter';

    public function mount(): void
    {
        $this->selectedGraduationYear = $this->resolveInitialGraduationYear();
    }

    public function updatedSelectedGraduationYear(): void
    {
        session([self::SESSION_KEY => $this->selectedGraduationYear]);
        $this->selectedFaculty = '';
        $this->selectedRecordId = null;
        $this->detailModalOpen = false;
    }

    public function updatedSelectedFaculty(): void
    {
        $this->selectedRecordId = null;
        $this->detailModalOpen = false;
    }

    private function resolveInitialGraduationYear(): ?int
    {
        $available = ProjectMapping::query()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($year) => (int) $year)
            ->all();

        if ($available === []) {
            return null;
        }

        $stored = (int) session(self::SESSION_KEY, 0);
        if ($stored > 0 && in_array($stored, $available, true)) {
            return $stored;
        }

        return $available[0];
    }

    public function openEvaluation(string $recordId): void
    {
        $this->selectedRecordId = $recordId;
        $this->detailModalOpen = true;
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canViewFacultyDetail(), 403);

        $availableMappings = ProjectMapping::query()
            ->orderByDesc('graduation_year')
            ->get(['id', 'academic_year', 'graduation_year']);

        $mapping = $this->selectedGraduationYear !== null
            ? ProjectMapping::byGraduationYear($this->selectedGraduationYear)
            : ProjectMapping::latestSourceProject();
        $sourceToken = (string) ($mapping?->redcap_token ?? '');
        $sourceRecords = $sourceToken === ''
            ? []
            : app(RedcapSourceService::class)->getCompletedEvaluationRecords($sourceToken);

        if ($user->isFaculty()) {
            $sourceRecords = collect($sourceRecords)
                ->filter(fn (array $record): bool => $this->recordBelongsToFaculty($record, $user))
                ->values()
                ->all();
        }

        $studentMap = app(RedcapDestinationService::class)->studentMapByDatatelId();
        $displayFaculty = $user->isFaculty()
            ? $this->facultyRoster($sourceRecords)[0] ?? $user->name
            : $this->selectedFaculty;
        $evaluations = $user->isFaculty()
            ? $this->evaluationRows($sourceRecords, $studentMap)
            : $this->evaluationsForFaculty($sourceRecords, $studentMap, $this->selectedFaculty);

        return view('livewire.faculty-detail', [
            'facultyRoster' => $this->facultyRoster($sourceRecords),
            'evaluations' => $evaluations,
            'selectedEvaluation' => $this->selectedEvaluation($evaluations),
            'categoryCounts' => collect($evaluations)->countBy('category_label')->sortKeys(),
            'displayFaculty' => $displayFaculty,
            'lockSelection' => $user->isFaculty(),
            'availableMappings' => $availableMappings,
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<int,string>
     */
    private function facultyRoster(array $records): array
    {
        return collect($records)
            ->map(fn (array $record): string => trim((string) ($record['faculty'] ?? '')))
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<int,array<string,mixed>>
     */
    private function evaluationsForFaculty(array $records, array $studentMap, string $faculty): array
    {
        if ($faculty === '') {
            return [];
        }

        return collect($records)
            ->filter(fn (array $record): bool => trim((string) ($record['faculty'] ?? '')) === $faculty)
            ->pipe(fn ($records): array => $this->evaluationRows($records->values()->all(), $studentMap));
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<int,array<string,mixed>>
     */
    private function evaluationRows(array $records, array $studentMap): array
    {
        return collect($records)
            ->map(fn (array $record): array => $this->evaluationRow($record, $studentMap))
            ->sortByDesc(fn (array $row): int => $row['date_sort'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $record
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<string,mixed>
     */
    private function evaluationRow(array $record, array $studentMap): array
    {
        $category = (string) ($record['eval_category'] ?? '');
        $scoreField = RedcapSourceService::SCORE_FIELDS[$category] ?? '';
        $studentId = trim((string) ($record['student'] ?? ''));
        $student = $studentMap[$studentId] ?? [];
        $rawDate = trim((string) ($record['date_lab'] ?? ''));

        return [
            'record_id' => (string) ($record['record_id'] ?? ''),
            'faculty' => trim((string) ($record['faculty'] ?? '')),
            'faculty_email' => trim((string) ($record['faculty_email'] ?? '')),
            'student_datatelid' => $studentId,
            'student_name' => $this->studentName($student, $studentId),
            'semester_label' => $this->semesterLabel((string) ($record['semester'] ?? '')),
            'category_label' => RedcapSourceService::CATEGORY_LABELS[$category] ?? ($category !== '' ? $category : 'Evaluation'),
            'score' => $scoreField !== '' ? trim((string) ($record[$scoreField] ?? '')) : '',
            'score_field' => $scoreField,
            'score_scale' => EvaluationNotification::SCORE_SCALE[$category] ?? '',
            'date' => $this->formatDate($rawDate),
            'date_sort' => $this->dateSort($rawDate),
            'comments' => trim((string) ($record['comments'] ?? '')),
            'criteria' => $this->criteriaRows($record, $category),
        ];
    }

    /**
     * @param  array<string,mixed>  $student
     */
    private function studentName(array $student, string $fallback): string
    {
        $first = trim((string) ($student['goes_by'] ?? '')) ?: trim((string) ($student['first_name'] ?? ''));
        $last = trim((string) ($student['last_name'] ?? ''));
        $name = trim($first.' '.$last);

        return $name !== '' ? $name : "Student {$fallback}";
    }

    private function semesterLabel(string $semester): string
    {
        $slug = EvalAggregator::SEMESTER_MAP[$semester] ?? '';

        return match ($slug) {
            'spring' => 'Spring',
            'fall' => 'Fall',
            default => $semester !== '' ? "Semester {$semester}" : 'Unknown Semester',
        };
    }

    private function formatDate(string $raw): string
    {
        if ($raw === '') {
            return 'Unknown date';
        }

        try {
            return Carbon::parse($raw)->toFormattedDateString();
        } catch (Throwable) {
            return $raw;
        }
    }

    private function dateSort(string $raw): int
    {
        try {
            return $raw !== '' ? Carbon::parse($raw)->timestamp : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string,mixed>  $record
     * @return array<int,array{label:string,value:string}>
     */
    private function criteriaRows(array $record, string $category): array
    {
        return collect(EvaluationNotification::CRITERIA[$category] ?? [])
            ->map(fn (string $label, string $field): array => [
                'label' => $label,
                'value' => (string) ($record[$field] ?? '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $evaluations
     * @return array<string,mixed>|null
     */
    private function selectedEvaluation(array $evaluations): ?array
    {
        if ($this->selectedRecordId === null) {
            return null;
        }

        return collect($evaluations)->firstWhere('record_id', $this->selectedRecordId) ?: null;
    }

    /**
     * @param  array<string,mixed>  $record
     */
    private function recordBelongsToFaculty(array $record, User $user): bool
    {
        $facultyEmail = strtolower(trim((string) ($record['faculty_email'] ?? '')));
        $facultyName = strtolower(trim((string) ($record['faculty'] ?? '')));

        return ($facultyEmail !== '' && $facultyEmail === strtolower($user->email))
            || ($facultyName !== '' && $facultyName === strtolower($user->name));
    }
}
