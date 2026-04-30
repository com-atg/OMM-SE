<?php

use App\Support\EvalAggregator;
use Illuminate\Support\Facades\Log;

function evalRecord(array $overrides = []): array
{
    return array_merge([
        'eval_category' => 'A',
        'teaching_score' => '90',
        'date_lab' => '2026-04-15',
        'faculty' => 'Dr. Smith',
        'comments' => '',
    ], $overrides);
}

it('aggregates count and average per category', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['teaching_score' => '80']),
        evalRecord(['teaching_score' => '90']),
        evalRecord(['eval_category' => 'B', 'clinical_performance_score' => '70', 'teaching_score' => '']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_teaching'])->toBe(2);
    expect($result['fields']['sem1_avg_teaching'])->toBe(85.0);
    expect($result['fields']['sem1_nu_clinic'])->toBe(1);
    expect($result['fields']['sem1_avg_clinic'])->toBe(70.0);
    expect($result['by_category']['teaching'])->toBe(['nu' => 2, 'avg' => 85.0]);
});

it('emits zero counts and omits avg for categories without evals', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['teaching_score' => '85']),
    ], 'sem2');

    expect($result['fields']['sem2_nu_teaching'])->toBe(1);
    expect($result['fields']['sem2_nu_clinic'])->toBe(0);
    expect($result['fields'])->not->toHaveKey('sem2_avg_clinic');
    expect($result['fields']['sem2_nu_research'])->toBe(0);
    expect($result['fields']['sem2_nu_didactics'])->toBe(0);
});

it('skips out-of-range scores and logs a warning', function () {
    Log::spy();

    $result = EvalAggregator::aggregate([
        evalRecord(['teaching_score' => '150']),
        evalRecord(['teaching_score' => '-5']),
        evalRecord(['teaching_score' => '75']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_teaching'])->toBe(1);
    expect($result['fields']['sem1_avg_teaching'])->toBe(75.0);
    Log::shouldHaveReceived('warning')->twice();
});

it('skips evals with empty score field', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['teaching_score' => '']),
        evalRecord(['teaching_score' => '88']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_teaching'])->toBe(1);
    expect($result['fields']['sem1_avg_teaching'])->toBe(88.0);
});

it('skips evals with unknown category', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['eval_category' => 'Z', 'teaching_score' => '90']),
        evalRecord(['eval_category' => '', 'teaching_score' => '95']),
        evalRecord(['teaching_score' => '80']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_teaching'])->toBe(1);
});

it('formats ISO dashed dates into M slash D slash Y for the dates field', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['date_lab' => '2026-04-15', 'faculty' => 'Dr. B', 'teaching_score' => '90']),
    ], 'sem1');

    expect($result['fields']['sem1_dates_teaching'])->toBe('Dr. B, 4/15/2026');
});

it('formats US-slashed dates into M slash D slash Y for the dates field', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['date_lab' => '04/15/2026', 'faculty' => 'Dr. C', 'teaching_score' => '90']),
    ], 'sem1');

    expect($result['fields']['sem1_dates_teaching'])->toBe('Dr. C, 4/15/2026');
});

it('keeps unparseable dates as-is in dates field', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['date_lab' => 'not-a-date', 'faculty' => 'Dr. D', 'teaching_score' => '90']),
    ], 'sem1');

    expect($result['fields']['sem1_dates_teaching'])->toBe('Dr. D, not-a-date');
});

it('joins multiple dates per category with semicolons', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['date_lab' => '2026-04-15', 'faculty' => 'Dr. A', 'teaching_score' => '90']),
        evalRecord(['date_lab' => '2026-05-20', 'faculty' => 'Dr. B', 'teaching_score' => '85']),
    ], 'sem1');

    expect($result['fields']['sem1_dates_teaching'])->toBe('Dr. A, 4/15/2026; Dr. B, 5/20/2026');
});

it('builds comment lines as Faculty; Date; Category; Text and joins with newlines', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['faculty' => 'Dr. A', 'date_lab' => '2026-04-15', 'comments' => 'great work']),
        evalRecord(['eval_category' => 'B', 'clinical_performance_score' => '80', 'teaching_score' => '', 'faculty' => 'Dr. B', 'date_lab' => '2026-05-20', 'comments' => 'improving']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_comments'])->toBe(2);
    expect($result['fields']['sem1_comments'])->toBe(
        "Dr. A; 4/15/2026; Teaching; great work\nDr. B; 5/20/2026; Clinic; improving"
    );
});

it('emits zero comments when none are present', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['comments' => '']),
    ], 'sem1');

    expect($result['fields']['sem1_nu_comments'])->toBe(0);
    expect($result['fields'])->not->toHaveKey('sem1_comments');
});

it('uses the slot key as field prefix', function () {
    $result = EvalAggregator::aggregate([
        evalRecord(['teaching_score' => '90']),
    ], 'sem3');

    expect($result['fields'])->toHaveKey('sem3_nu_teaching');
    expect($result['fields'])->toHaveKey('sem3_avg_teaching');
    expect($result['fields'])->toHaveKey('sem3_nu_comments');
    expect($result['slot_key'])->toBe('sem3');
});

it('uses fallback "Faculty" label when faculty key is absent', function () {
    $eval = ['eval_category' => 'A', 'teaching_score' => '90', 'date_lab' => '2026-04-15', 'comments' => ''];

    $result = EvalAggregator::aggregate([$eval], 'sem1');

    expect($result['fields']['sem1_dates_teaching'])->toBe('Faculty, 4/15/2026');
});
