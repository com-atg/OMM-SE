<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Maps an incoming source eval (semester code + lab date) to a 1–4 slot index
 * within a scholar's 4-semester evaluation window, based on their cohort start
 * term + year.
 *
 * Term ordinal: year * 2 + (Fall ? 1 : 0). Slot = ordinal(eval) - ordinal(cohort) + 1.
 * A slot outside [1,4] means the eval is before the cohort start or after slot 4
 * (graduation) and should be rejected.
 */
class SemesterSlot
{
    public const SOURCE_SEMESTER_TERM = ['1' => 'Spring', '2' => 'Fall'];

    public const TERMS = ['Spring', 'Fall'];

    public const SLOTS = [1, 2, 3, 4];

    /**
     * Compute the slot (1–4) for an eval, given the scholar's cohort start.
     * Returns null if the inputs are invalid or the eval falls outside the window.
     */
    public static function compute(string $sourceSemesterCode, string $dateLab, ?string $cohortTerm, ?int $cohortYear): ?int
    {
        $evalTerm = self::SOURCE_SEMESTER_TERM[$sourceSemesterCode] ?? null;

        if ($evalTerm === null || $cohortTerm === null || $cohortYear === null) {
            return null;
        }

        if (! in_array($cohortTerm, self::TERMS, true)) {
            return null;
        }

        $evalYear = self::yearFromDate($dateLab);

        if ($evalYear === null) {
            return null;
        }

        $slot = self::ordinal($evalTerm, $evalYear) - self::ordinal($cohortTerm, $cohortYear) + 1;

        return ($slot >= 1 && $slot <= 4) ? $slot : null;
    }

    /**
     * Build the per-slot UI labels for a cohort, e.g.
     * ['Fall', 2026] => [1=>'Fall 2026', 2=>'Spring 2027', 3=>'Fall 2027', 4=>'Spring 2028'].
     *
     * @return array<int,string>
     */
    public static function labelsFor(?string $cohortTerm, ?int $cohortYear): array
    {
        if ($cohortTerm === null || $cohortYear === null || ! in_array($cohortTerm, self::TERMS, true)) {
            return [1 => 'Semester 1', 2 => 'Semester 2', 3 => 'Semester 3', 4 => 'Semester 4'];
        }

        $term = $cohortTerm;
        $year = $cohortYear;
        $labels = [];

        foreach (self::SLOTS as $slot) {
            $labels[$slot] = "{$term} {$year}";
            [$term, $year] = self::nextTerm($term, $year);
        }

        return $labels;
    }

    /**
     * Build the per-slot keys ('sem1' … 'sem4') used as destination field-name prefixes.
     *
     * @return array<int,string>
     */
    public static function slotKeys(): array
    {
        return [1 => 'sem1', 2 => 'sem2', 3 => 'sem3', 4 => 'sem4'];
    }

    public static function slotKey(int $slot): string
    {
        return "sem{$slot}";
    }

    /**
     * Extract a 4-digit year from a REDCap date string (Y-m-d, m-d-Y, or m/d/Y).
     */
    public static function yearFromDate(string $dateLab): ?int
    {
        $dateLab = trim($dateLab);

        if ($dateLab === '') {
            return null;
        }

        foreach (['Y-m-d', 'm-d-Y', 'm/d/Y', 'Y/m/d'] as $format) {
            try {
                return (int) Carbon::createFromFormat('!'.$format, $dateLab)->format('Y');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return (int) Carbon::parse($dateLab)->format('Y');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function nextTerm(string $term, int $year): array
    {
        return $term === 'Fall'
            ? ['Spring', $year + 1]
            : ['Fall', $year];
    }

    private static function ordinal(string $term, int $year): int
    {
        return $year * 2 + ($term === 'Fall' ? 1 : 0);
    }
}
