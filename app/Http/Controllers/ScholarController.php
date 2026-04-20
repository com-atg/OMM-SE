<?php

namespace App\Http\Controllers;

use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScholarController extends Controller
{
    private const SEMESTERS = ['spring' => 'Spring', 'fall' => 'Fall'];

    public function __invoke(Request $request, RedcapDestinationService $destination): View
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $records = $destination->getAllScholarRecords();

        if ($user->isStudent()) {
            $ownId = (string) ($user->redcap_record_id ?? '');
            $match = $ownId !== '' ? collect($records)->firstWhere('record_id', $ownId) : null;

            abort_unless($match, 404, 'No evaluation records found for your account.');

            return view('scholar', [
                'roster' => [],
                'selected' => [
                    'record_id' => (string) $match['record_id'],
                    'name' => $this->displayName($match),
                ],
                'semesters' => $this->buildSemesters($match),
                'lock_selection' => true,
            ]);
        }

        abort_unless($user->canViewAllScholars(), 403);

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
            $match = collect($records)->firstWhere('record_id', $selectedId);

            if ($match) {
                $selected = [
                    'record_id' => (string) $match['record_id'],
                    'name' => $this->displayName($match),
                ];
                $semesters = $this->buildSemesters($match);
            }
        }

        return view('scholar', [
            'roster' => $roster,
            'selected' => $selected,
            'semesters' => $semesters,
            'lock_selection' => false,
        ]);
    }

    private function displayName(array $record): string
    {
        $first = trim((string) ($record['goes_by'] ?? '')) ?: trim((string) ($record['first_name'] ?? ''));
        $last = trim((string) ($record['last_name'] ?? ''));

        return trim($first.' '.$last);
    }

    /**
     * Build per-semester eval counts and averages across all categories.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildSemesters(array $record): array
    {
        $categories = RedcapSourceService::DEST_CATEGORY;
        $labels = array_values(RedcapSourceService::CATEGORY_LABELS);
        $categoryKeys = array_values($categories);

        $out = [];

        foreach (self::SEMESTERS as $slug => $label) {
            $counts = [];
            $averages = [];
            $total = 0;

            foreach ($categoryKeys as $catKey) {
                $nu = (int) ($record["{$slug}_nu_{$catKey}"] ?? 0);
                $avgRaw = $record["{$slug}_avg_{$catKey}"] ?? '';
                $counts[] = $nu;
                $averages[] = ($avgRaw !== '' && is_numeric($avgRaw)) ? (float) $avgRaw : null;
                $total += $nu;
            }

            $out[] = [
                'slug' => $slug,
                'label' => $label,
                'category_labels' => $labels,
                'counts' => $counts,
                'averages' => $averages,
                'total' => $total,
                'comments_count' => (int) ($record["{$slug}_nu_comments"] ?? 0),
            ];
        }

        return $out;
    }
}
