<?php

namespace App\Livewire;

use App\Mail\EvaluationNotification;
use App\Models\ProjectMapping;
use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\SemesterSlot;
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

    public bool $activeOnly = true;

    public ?string $selectedBatch = null;

    public function mount(): void
    {
        $this->activeOnly = (bool) session(Dashboard::SESSION_KEY_ACTIVE_ONLY, true);
        $batch = (string) session(Dashboard::SESSION_KEY_BATCH, '');
        $this->selectedBatch = $batch !== '' ? $batch : null;
    }

    public function updatedActiveOnly(): void
    {
        session([Dashboard::SESSION_KEY_ACTIVE_ONLY => $this->activeOnly]);
        $this->resetSelection();
    }

    public function updatedSelectedBatch(): void
    {
        session([Dashboard::SESSION_KEY_BATCH => $this->selectedBatch ?? '']);
        $this->resetSelection();
    }

    public function updatedSelectedFaculty(): void
    {
        $this->selectedRecordId = null;
        $this->detailModalOpen = false;
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

        $destination = app(RedcapDestinationService::class);
        $availableBatches = $destination->availableBatches();

        if ($this->selectedBatch !== null && ! in_array($this->selectedBatch, $availableBatches, true)) {
            $this->selectedBatch = null;
            session([Dashboard::SESSION_KEY_BATCH => '']);
        }

        $mapping = ProjectMapping::activeSource();
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

        $studentMap = $this->filteredStudentMap($destination->studentMapByDatatelId());
        $sourceRecords = $this->filterSourceRecordsByStudent($sourceRecords, $studentMap);

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
            'availableBatches' => $availableBatches,
        ]);
    }

    private function resetSelection(): void
    {
        $this->selectedFaculty = '';
        $this->selectedRecordId = null;
        $this->detailModalOpen = false;
    }

    /**
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<string,array<string,mixed>>
     */
    private function filteredStudentMap(array $studentMap): array
    {
        return collect($studentMap)
            ->filter(function (array $record): bool {
                if ($this->activeOnly && (string) ($record['is_active'] ?? '') !== '1') {
                    return false;
                }

                if ($this->selectedBatch !== null && trim((string) ($record['batch'] ?? '')) !== $this->selectedBatch) {
                    return false;
                }

                return true;
            })
            ->all();
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @param  array<string,array<string,mixed>>  $studentMap
     * @return array<int,array<string,mixed>>
     */
    private function filterSourceRecordsByStudent(array $records, array $studentMap): array
    {
        return collect($records)
            ->filter(function (array $record) use ($studentMap): bool {
                $studentId = trim((string) ($record['student'] ?? ''));

                return $studentId !== '' && isset($studentMap[$studentId]);
            })
            ->values()
            ->all();
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
        return match (SemesterSlot::SOURCE_SEMESTER_TERM[$semester] ?? '') {
            'Spring' => 'Spring',
            'Fall' => 'Fall',
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
