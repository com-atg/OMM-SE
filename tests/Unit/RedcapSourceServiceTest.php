<?php

use App\Services\RedcapSourceService;

test('SCORE_FIELDS maps all four categories to correct source fields', function () {
    expect(RedcapSourceService::SCORE_FIELDS)->toBe([
        'A' => 'teaching_score',
        'B' => 'clinical_performance_score',
        'C' => 'research_total_score',
        'D' => 'didactic_total_score',
    ]);
});

test('CATEGORY_LABELS maps all four categories to human-readable names', function () {
    expect(RedcapSourceService::CATEGORY_LABELS)->toBe([
        'A' => 'Teaching',
        'B' => 'Clinic',
        'C' => 'Research',
        'D' => 'Didactics',
    ]);
});

test('DEST_CATEGORY maps all four categories to destination field suffixes', function () {
    expect(RedcapSourceService::DEST_CATEGORY)->toBe([
        'A' => 'teaching',
        'B' => 'clinic',
        'C' => 'research',
        'D' => 'didactics',
    ]);
});

test('SCORE_FIELDS DEST_CATEGORY and CATEGORY_LABELS all share the same category keys', function () {
    $keys = array_keys(RedcapSourceService::SCORE_FIELDS);

    expect(array_keys(RedcapSourceService::DEST_CATEGORY))->toBe($keys)
        ->and(array_keys(RedcapSourceService::CATEGORY_LABELS))->toBe($keys);
});

// ─── getScholarEvals input validation ─────────────────────────────────────────

test('getScholarEvals returns empty array for non-numeric datatelid', function () {
    $service = new RedcapSourceService;

    expect($service->getScholarEvals("1' OR '1'='1", '1'))->toBe([]);
});

test('getScholarEvals returns empty array for invalid semester code', function () {
    $service = new RedcapSourceService;

    expect($service->getScholarEvals('1', '9'))->toBe([]);
});

test('getScholarEvals returns empty array when semester contains injection attempt', function () {
    $service = new RedcapSourceService;

    expect($service->getScholarEvals('1', "1' OR '1'='1"))->toBe([]);
});
