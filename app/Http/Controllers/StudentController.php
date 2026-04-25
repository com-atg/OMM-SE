<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    private const SEMESTERS = ['spring' => 'Spring', 'fall' => 'Fall'];

    /**
     * Standard student page — students see their own record, Service/Admin see the full roster.
     */
    public function __invoke(Request $request, RedcapDestinationService $destination): View
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $records = $destination->getAllStudentRecords();

        if ($user->isStudent()) {
            $match = $this->resolveRecord($records, (string) ($user->redcap_record_id ?? ''));

            abort_unless($match, 404, 'No evaluation records found for your account.');

            return view('student', [
                'roster' => [],
                'selected' => $this->selectedStudent($match),
                'semesters' => $this->buildSemesters($match),
                'lock_selection' => true,
                'shareable_url' => null,
            ]);
        }

        abort_unless($user->canViewAllStudents(), 403);

        $roster = collect($records)
            ->map(fn ($r) => [
                'record_id' => (string) ($r['record_id'] ?? ''),
                'name' => $this->displayName($r),
            ])
            ->filter(fn ($r) => $r['record_id'] !== '' && $r['name'] !== '')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $selectedId = (string) $request->query('id', '');
        $selected = null;
        $semesters = [];

        if ($selectedId !== '') {
            $match = $this->resolveRecord($records, $selectedId);

            if ($match) {
                $selected = $this->selectedStudent($match);
                $semesters = $this->buildSemesters($match);
            }
        }

        return view('student', [
            'roster' => $roster,
            'selected' => $selected,
            'semesters' => $semesters,
            'lock_selection' => false,
            'shareable_url' => null,
        ]);
    }

    /**
     * Token-resolved student page — resolves a specific student by their public UUID.
     * Service/Admin may view any token; Students may only view their own.
     */
    public function show(string $token, RedcapDestinationService $destination): View
    {
        $viewer = Auth::user();
        abort_unless($viewer !== null, 403);

        $target = User::where('public_token', $token)->firstOrFail();

        // Students can only resolve their own token.
        if ($viewer->isStudent() && $viewer->id !== $target->id) {
            abort(403);
        }

        abort_unless($viewer->canViewAllStudents() || $viewer->id === $target->id, 403);

        $records = $destination->getAllStudentRecords();
        $match = $this->resolveRecord($records, (string) ($target->redcap_record_id ?? ''));

        abort_unless($match, 404, 'No evaluation records found for this token.');

        return view('student', [
            'roster' => [],
            'selected' => $this->selectedStudent($match),
            'semesters' => $this->buildSemesters($match),
            'lock_selection' => true,
            'shareable_url' => $viewer->isStudent() ? null : route('student.token', $target->public_token),
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $records
     * @return array<string,mixed>|null
     */
    private function resolveRecord(array $records, string $recordId): ?array
    {
        if ($recordId === '') {
            return null;
        }

        return collect($records)->firstWhere('record_id', $recordId) ?: null;
    }

    private function displayName(array $record): string
    {
        $first = trim((string) ($record['goes_by'] ?? '')) ?: trim((string) ($record['first_name'] ?? ''));
        $last = trim((string) ($record['last_name'] ?? ''));

        return trim($first.' '.$last);
    }

    /**
     * @param  array<string,mixed>  $record
     * @return array{record_id:string,name:string,datatelid:string|null,photo_url:string|null}
     */
    private function selectedStudent(array $record): array
    {
        $datatelId = trim((string) ($record['datatelid'] ?? ''));

        return [
            'record_id' => (string) $record['record_id'],
            'name' => $this->displayName($record),
            'datatelid' => $datatelId !== '' ? $datatelId : null,
            'photo_url' => $datatelId !== '' ? 'https://guru.nyit.edu/GuruAdmin/StudentOverview/StudentPhotoImageHandler.ashx?id='.$datatelId : null,
        ];
    }

    /**
     * Build per-semester eval counts, averages, scores, dates, comments,
     * and monthly activity data for the student view.
     *
     * @param  array<string,mixed>  $record
     * @return array<int,array<string,mixed>>
     */
    private function buildSemesters(array $record): array
    {
        $categories = RedcapSourceService::DEST_CATEGORY;       // ['A'=>'teaching', ...]
        $labels = array_values(RedcapSourceService::CATEGORY_LABELS); // ['Teaching', ...]
        $categoryKeys = array_values($categories);

        $out = [];

        foreach (self::SEMESTERS as $slug => $label) {
            $counts = [];
            $averages = [];
            $dates = [];
            $total = 0;

            foreach ($categoryKeys as $catKey) {
                $nu = (int) ($record["{$slug}_nu_{$catKey}"] ?? 0);
                $avgRaw = $record["{$slug}_avg_{$catKey}"] ?? '';
                $datesRaw = trim((string) ($record["{$slug}_dates_{$catKey}"] ?? ''));

                $counts[] = $nu;
                $averages[] = ($avgRaw !== '' && is_numeric($avgRaw)) ? (float) $avgRaw : null;
                $dates[$catKey] = $datesRaw !== '' ? array_map('trim', explode(';', $datesRaw)) : [];
                $total += $nu;
            }

            $finalScore = ($record["{$slug}_final_score"] ?? '') !== ''
                ? (float) $record["{$slug}_final_score"]
                : null;

            $leadership = ($record["{$slug}_leadership"] ?? '') !== ''
                ? (int) $record["{$slug}_leadership"]
                : null;

            $out[] = [
                'slug' => $slug,
                'label' => $label,
                'category_labels' => $labels,
                'category_keys' => $categoryKeys,
                'counts' => $counts,
                'averages' => $averages,
                'dates' => $dates,
                'total' => $total,
                'final_score' => $finalScore,
                'leadership' => $leadership,
                'comments_count' => (int) ($record["{$slug}_nu_comments"] ?? 0),
                'comments' => $this->parseComments(trim((string) ($record["{$slug}_comments"] ?? ''))),
                'monthly' => $this->buildMonthly($dates, $categoryKeys),
            ];
        }

        return $out;
    }

    /**
     * Parse the stored comments string into structured rows.
     * Each line has format "Faculty; Date; Category; Comment text".
     *
     * @return array<int,array{faculty:string,date:string,category:string,comment:string}>
     */
    private function parseComments(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $rows = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(';', $line, 4));
            $rows[] = [
                'faculty' => $parts[0] ?? '',
                'date' => $parts[1] ?? '',
                'category' => $parts[2] ?? '',
                'comment' => $parts[3] ?? $line,
            ];
        }

        return $rows;
    }

    /**
     * Build a sorted month → [catKey → count] map from the per-category date entry arrays.
     * Each entry is "Faculty, M/D/YYYY"; we extract the date portion (everything after the last comma).
     *
     * @param  array<string,list<string>>  $dates  catKey → ["Faculty, M/D/YYYY", ...]
     * @param  list<string>  $categoryKeys
     * @return array<string,array<string,int>> "Mon YYYY" => [catKey => count]
     */
    private function buildMonthly(array $dates, array $categoryKeys): array
    {
        $buckets = [];

        foreach ($categoryKeys as $catKey) {
            foreach ($dates[$catKey] ?? [] as $entry) {
                $datePart = trim((string) strrchr($entry, ','), ', ');

                if ($datePart === '') {
                    continue;
                }

                try {
                    $monthKey = Carbon::parse($datePart)->format('M Y');
                } catch (\Throwable) {
                    continue;
                }

                $buckets[$monthKey] ??= [];
                $buckets[$monthKey][$catKey] = ($buckets[$monthKey][$catKey] ?? 0) + 1;
            }
        }

        // Sort chronologically.
        uksort($buckets, fn ($a, $b) => Carbon::parse("1 $a")->timestamp <=> Carbon::parse("1 $b")->timestamp);

        return $buckets;
    }
}
