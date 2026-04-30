<?php

use App\Support\SemesterSlot;

it('maps Fall-start cohort across 4 slots', function () {
    expect(SemesterSlot::compute('2', '2026-10-15', 'Fall', 2026))->toBe(1);
    expect(SemesterSlot::compute('1', '2027-04-15', 'Fall', 2026))->toBe(2);
    expect(SemesterSlot::compute('2', '2027-10-15', 'Fall', 2026))->toBe(3);
    expect(SemesterSlot::compute('1', '2028-04-15', 'Fall', 2026))->toBe(4);
});

it('maps Spring-start cohort across 4 slots', function () {
    expect(SemesterSlot::compute('1', '2026-04-15', 'Spring', 2026))->toBe(1);
    expect(SemesterSlot::compute('2', '2026-10-15', 'Spring', 2026))->toBe(2);
    expect(SemesterSlot::compute('1', '2027-04-15', 'Spring', 2026))->toBe(3);
    expect(SemesterSlot::compute('2', '2027-10-15', 'Spring', 2026))->toBe(4);
});

it('rejects evals before cohort start', function () {
    expect(SemesterSlot::compute('1', '2025-04-15', 'Fall', 2026))->toBeNull();
    expect(SemesterSlot::compute('2', '2025-10-15', 'Spring', 2026))->toBeNull();
});

it('rejects evals after slot 4', function () {
    expect(SemesterSlot::compute('2', '2028-10-15', 'Fall', 2026))->toBeNull();
    expect(SemesterSlot::compute('1', '2028-04-15', 'Spring', 2026))->toBeNull();
});

it('returns null for unknown semester code', function () {
    expect(SemesterSlot::compute('9', '2026-10-15', 'Fall', 2026))->toBeNull();
    expect(SemesterSlot::compute('', '2026-10-15', 'Fall', 2026))->toBeNull();
});

it('returns null when cohort term is missing or invalid', function () {
    expect(SemesterSlot::compute('2', '2026-10-15', null, 2026))->toBeNull();
    expect(SemesterSlot::compute('2', '2026-10-15', 'Summer', 2026))->toBeNull();
});

it('returns null when cohort year is missing', function () {
    expect(SemesterSlot::compute('2', '2026-10-15', 'Fall', null))->toBeNull();
});

it('returns null when date is unparseable', function () {
    expect(SemesterSlot::compute('2', '', 'Fall', 2026))->toBeNull();
    expect(SemesterSlot::compute('2', 'not-a-date', 'Fall', 2026))->toBeNull();
});

it('parses several REDCap date formats', function () {
    expect(SemesterSlot::yearFromDate('2026-10-15'))->toBe(2026);
    expect(SemesterSlot::yearFromDate('10-15-2026'))->toBe(2026);
    expect(SemesterSlot::yearFromDate('10/15/2026'))->toBe(2026);
});

it('builds cohort labels for Fall-start cohort', function () {
    expect(SemesterSlot::labelsFor('Fall', 2026))->toBe([
        1 => 'Fall 2026',
        2 => 'Spring 2027',
        3 => 'Fall 2027',
        4 => 'Spring 2028',
    ]);
});

it('builds cohort labels for Spring-start cohort', function () {
    expect(SemesterSlot::labelsFor('Spring', 2027))->toBe([
        1 => 'Spring 2027',
        2 => 'Fall 2027',
        3 => 'Spring 2028',
        4 => 'Fall 2028',
    ]);
});

it('falls back to generic labels when cohort is unknown', function () {
    expect(SemesterSlot::labelsFor(null, null))->toBe([
        1 => 'Semester 1',
        2 => 'Semester 2',
        3 => 'Semester 3',
        4 => 'Semester 4',
    ]);
});

it('exposes slot keys as sem1..sem4', function () {
    expect(SemesterSlot::slotKeys())->toBe([1 => 'sem1', 2 => 'sem2', 3 => 'sem3', 4 => 'sem4']);
    expect(SemesterSlot::slotKey(3))->toBe('sem3');
});
