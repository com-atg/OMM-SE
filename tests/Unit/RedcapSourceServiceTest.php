<?php

use App\Services\RedcapSourceService;
use Illuminate\Support\Facades\Cache;

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

// ─── getStudentEvals input validation ─────────────────────────────────────────

test('getStudentEvals returns empty array for non-numeric datatelid', function () {
    $service = new RedcapSourceService;

    expect($service->getStudentEvals("1' OR '1'='1", '1', 'TOKEN'))->toBe([]);
});

test('getStudentEvals returns empty array for invalid semester code', function () {
    $service = new RedcapSourceService;

    expect($service->getStudentEvals('1', '9', 'TOKEN'))->toBe([]);
});

test('getStudentEvals returns empty array when semester contains injection attempt', function () {
    $service = new RedcapSourceService;

    expect($service->getStudentEvals('1', "1' OR '1'='1", 'TOKEN'))->toBe([]);
});

test('getCompletedEvaluationRecords returns only completed source evaluations', function () {
    Cache::flush();

    $service = new class extends RedcapSourceService
    {
        public function fetchAllRecords(string $token): array
        {
            expect($token)->toBe('SOURCE_TOKEN');

            return [
                [
                    'record_id' => '1',
                    'student' => '100',
                    'semester' => '1',
                    'eval_category' => 'A',
                    'faculty' => 'Dr. Smith',
                    'teaching_score' => '90',
                    'omm_evaluation_complete' => '2',
                ],
                [
                    'record_id' => '2',
                    'student' => '100',
                    'semester' => '1',
                    'eval_category' => 'A',
                    'faculty' => 'Dr. Smith',
                    'teaching_score' => '80',
                    'omm_evaluation_complete' => '1',
                ],
                [
                    'record_id' => '3',
                    'student' => '200',
                    'semester' => '2',
                    'eval_category' => 'B',
                    'faculty' => '',
                    'clinical_performance_score' => '85',
                    'omm_evaluation_complete' => '2',
                ],
            ];
        }
    };

    $records = $service->getCompletedEvaluationRecords('SOURCE_TOKEN');

    expect($records)
        ->toHaveCount(1)
        ->and($records[0]['record_id'])->toBe('1');
});
