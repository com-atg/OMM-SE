<?php

use App\Mail\EvaluationNotification;
use App\Services\RedcapSourceService;

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeEvalRecord(string $category = 'A', array $overrides = []): array
{
    $base = [
        'record_id' => '1',
        'date_lab' => '04-16-2026',
        'semester' => '1',
        'student' => '1',
        'eval_category' => $category,
        'comments' => '',
        'faculty' => 'Dr. Smith',
        'faculty_email' => 'faculty@example.com',
        // Teaching scores
        'small' => '5',
        'large' => '4',
        'knowledge' => '4',
        'studevals' => '5',
        'profess' => '6',
        'teaching_score' => '90.00',
        // Clinic scores
        'effhx' => '4', 'apphx' => '3', 'diffdx' => '4', 'gentxplan' => '3',
        'ex_know' => '4', 'ev_base' => '3', 'team' => '4', 'comm' => '3',
        'writ_com' => '4', 'oral' => '3', 'opp' => '4', 'respect' => '4',
        'resp_feedback' => '3', 'account' => '4',
        'clinical_performance_score' => '83.93',
        // Research scores
        're_focus' => '5', 're_meth' => '4', 're_reults' => '5', 're_concl' => '4',
        're_doc' => '5', 're_man_format' => '4', 're_prof' => '6', 're_prep' => '5',
        'research_total_score' => '80.00',
        // Didactics scores
        'study_overview' => '5', 'study_analys' => '4', 'study_concl' => '5',
        'preparedness' => '6', 'presentation' => '4', 'hands_on' => '5',
        'didactic_total_score' => '76.67',
    ];

    return array_merge($base, $overrides);
}

function makeScholarRecord(array $overrides = []): array
{
    return array_merge([
        'record_id' => '1',
        'first_name' => 'Catherine',
        'last_name' => 'Chin',
        'goes_by' => 'Cat',
        'email' => 'catherine@example.com',
    ], $overrides);
}

function makeAggregates(string $semester = 'spring'): array
{
    return [
        'semester' => $semester,
        'by_category' => [
            'teaching' => ['nu' => 1, 'avg' => 90.00],
            'clinic' => ['nu' => 0, 'avg' => null],
            'research' => ['nu' => 0, 'avg' => null],
            'didactics' => ['nu' => 0, 'avg' => null],
        ],
        'fields' => [],
    ];
}

// ─── Subject line ────────────────────────────────────────────────────────────

test('subject includes category label for each eval_category', function (string $category, string $label) {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord($category),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: $category,
    );

    expect($mailable->envelope()->subject)->toBe("[OMM Scholar Eval] {$label} Evaluation");
})->with([
    'Teaching' => ['A', 'Teaching'],
    'Clinic' => ['B', 'Clinic'],
    'Research' => ['C', 'Research'],
    'Didactics' => ['D', 'Didactics'],
]);

// ─── CRITERIA constant ───────────────────────────────────────────────────────

test('CRITERIA contains entries for all four categories', function () {
    expect(EvaluationNotification::CRITERIA)->toHaveKeys(['A', 'B', 'C', 'D']);
});

test('Teaching criteria contains all five sub-criteria fields', function () {
    expect(EvaluationNotification::CRITERIA['A'])
        ->toHaveKeys(['small', 'large', 'knowledge', 'studevals', 'profess']);
});

test('Clinic criteria contains all fourteen sub-criteria fields', function () {
    expect(EvaluationNotification::CRITERIA['B'])->toHaveCount(14);
});

test('Research criteria contains all eight sub-criteria fields', function () {
    expect(EvaluationNotification::CRITERIA['C'])->toHaveCount(8);
});

test('Didactics criteria contains all six sub-criteria fields', function () {
    expect(EvaluationNotification::CRITERIA['D'])->toHaveCount(6);
});

// ─── SCORE_SCALE constant ────────────────────────────────────────────────────

test('SCORE_SCALE defines a scale for all four categories', function () {
    expect(EvaluationNotification::SCORE_SCALE)->toHaveKeys(['A', 'B', 'C', 'D']);
});

test('Clinic score scale references 1 to 4', function () {
    expect(EvaluationNotification::SCORE_SCALE['B'])->toContain('1–4');
});

// ─── Markdown content ────────────────────────────────────────────────────────

test('content uses the evaluation markdown view', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord(),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    expect($mailable->content()->markdown)->toBe('emails.evaluation');
});

test('content passes criteria scoreScale categoryLabel and scoreField to view', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord('A'),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    $with = $mailable->content()->with;

    expect($with)
        ->toHaveKey('criteria')
        ->toHaveKey('scoreScale')
        ->toHaveKey('categoryLabel')
        ->toHaveKey('scoreField');

    expect($with['categoryLabel'])->toBe('Teaching')
        ->and($with['scoreField'])->toBe(RedcapSourceService::SCORE_FIELDS['A']);
});

test('goes_by is used as greeting when set', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord(),
        scholarRecord: makeScholarRecord(['goes_by' => 'Cat']),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    $rendered = $mailable->render();

    expect($rendered)->toContain('Cat');
});

test('first_name is used as greeting when goes_by is empty', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord(),
        scholarRecord: makeScholarRecord(['goes_by' => '', 'first_name' => 'Catherine']),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    $rendered = $mailable->render();

    expect($rendered)->toContain('Catherine');
});

test('faculty feedback panel is shown when comments are present', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord('A', ['comments' => 'Outstanding performance!']),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    $rendered = $mailable->render();

    expect($rendered)->toContain('Outstanding performance!');
});

test('faculty feedback panel is absent when comments are empty', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord('A', ['comments' => '']),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    $rendered = $mailable->render();

    expect($rendered)->not->toContain('Faculty Feedback');
});

test('renders evaluation date when redcap sends iso date format', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord('A', ['date_lab' => '2026-04-22']),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    expect($mailable->render())->toContain('Apr 22, 2026');
});

test('semester summary table shows correct category averages', function () {
    $aggregates = [
        'semester' => 'spring',
        'by_category' => [
            'teaching' => ['nu' => 2, 'avg' => 88.50],
            'clinic' => ['nu' => 1, 'avg' => 75.00],
            'research' => ['nu' => 0, 'avg' => null],
            'didactics' => ['nu' => 3, 'avg' => 92.00],
        ],
        'fields' => [],
    ];

    $rendered = (new EvaluationNotification(
        evalRecord: makeEvalRecord(),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: $aggregates,
        evalCategory: 'A',
    ))->render();

    expect($rendered)
        ->toContain('88.5%')
        ->toContain('75%')
        ->toContain('92%')
        ->toContain('—'); // null avg displays as dash
});

test('returns no attachments', function () {
    $mailable = new EvaluationNotification(
        evalRecord: makeEvalRecord(),
        scholarRecord: makeScholarRecord(),
        semester: 'spring',
        aggregates: makeAggregates(),
        evalCategory: 'A',
    );

    expect($mailable->attachments())->toBeEmpty();
});
