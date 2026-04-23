<?php

use App\Services\RedcapDestinationService;
use App\Support\FinalScoreFormulaParser;

it('derives final score weight distribution from REDCap metadata', function () {
    $formulas = FinalScoreFormulaParser::fromMetadata([
        [
            'field_name' => 'spring_avg_teaching',
            'field_label' => 'Spring Teaching Average',
            'text_validation_max' => '100',
        ],
        [
            'field_name' => 'spring_avg_clinic',
            'field_label' => 'Spring Clinic Average',
            'text_validation_max' => '100',
        ],
        [
            'field_name' => 'spring_avg_research',
            'field_label' => 'Spring Research Average',
            'text_validation_max' => '100',
        ],
        [
            'field_name' => 'spring_avg_didactics',
            'field_label' => 'Spring Didactics Average',
            'text_validation_max' => '100',
        ],
        [
            'field_name' => 'spring_leadership',
            'field_label' => 'Spring Leadership',
            'text_validation_max' => '10',
        ],
        [
            'field_name' => 'spring_final_score',
            'select_choices_or_calculations' => 'round(([spring_avg_teaching]*0.25)+([spring_avg_clinic]*0.25)+([spring_avg_research]*0.2)+([spring_avg_didactics]*0.2)+[spring_leadership], 2)',
        ],
    ]);

    expect($formulas['spring']['field'])->toBe('spring_final_score')
        ->and($formulas['spring']['components'])->toHaveCount(5)
        ->and($formulas['spring']['components'][0])->toMatchArray([
            'field' => 'spring_avg_teaching',
            'label' => 'Teaching',
            'max_points' => 25.0,
            'weight_percent' => 25.0,
        ])
        ->and($formulas['spring']['components'][4])->toMatchArray([
            'field' => 'spring_leadership',
            'label' => 'Leadership',
            'max_points' => 10.0,
            'weight_percent' => 10.0,
        ]);
});

it('refreshes destination score formulas on normal page reloads by default', function () {
    $parameter = (new ReflectionMethod(RedcapDestinationService::class, 'finalScoreFormulas'))
        ->getParameters()[0];

    expect($parameter->getDefaultValue())->toBe(0);
});
