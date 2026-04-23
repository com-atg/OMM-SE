<?php

namespace App\Support;

use App\Services\RedcapSourceService;
use Illuminate\Support\Str;

class FinalScoreFormulaParser
{
    private const FINAL_SCORE_FIELDS = [
        'spring' => 'spring_final_score',
        'fall' => 'fall_final_score',
    ];

    /**
     * @param  array<int,array<string,mixed>>  $metadata
     * @return array<string,array{field:string,formula:string,components:array<int,array{field:string,label:string,coefficient:float,max_value:float,max_points:float,weight_percent:float}>}>
     */
    public static function fromMetadata(array $metadata): array
    {
        $metadataByField = collect($metadata)
            ->filter(fn (array $field): bool => filled($field['field_name'] ?? null))
            ->keyBy(fn (array $field): string => (string) $field['field_name'])
            ->all();

        $formulas = [];

        foreach (self::FINAL_SCORE_FIELDS as $semester => $fieldName) {
            $field = $metadataByField[$fieldName] ?? null;
            $formula = trim((string) ($field['select_choices_or_calculations'] ?? ''));

            if ($formula === '') {
                continue;
            }

            $components = self::components($formula, $semester, $fieldName, $metadataByField);
            $totalMaxPoints = collect($components)->sum('max_points');

            if ($totalMaxPoints > 0) {
                $components = collect($components)
                    ->map(fn (array $component): array => $component + [
                        'weight_percent' => round($component['max_points'] / $totalMaxPoints * 100, 1),
                    ])
                    ->values()
                    ->all();
            }

            $formulas[$semester] = [
                'field' => $fieldName,
                'formula' => $formula,
                'components' => $components,
            ];
        }

        return $formulas;
    }

    /**
     * @param  array<string,array<string,mixed>>  $metadataByField
     * @return array<int,array{field:string,label:string,coefficient:float,max_value:float,max_points:float}>
     */
    private static function components(string $formula, string $semester, string $finalScoreField, array $metadataByField): array
    {
        preg_match_all('/\[([A-Za-z][A-Za-z0-9_]*)\]/', $formula, $matches);

        return collect($matches[1] ?? [])
            ->unique()
            ->filter(fn (string $field): bool => $field !== $finalScoreField && Str::startsWith($field, $semester.'_'))
            ->map(function (string $field) use ($formula, $semester, $metadataByField): ?array {
                $coefficient = self::coefficientForField($formula, $field);

                if ($coefficient <= 0.0) {
                    return null;
                }

                $maxValue = self::maxValue($field, $metadataByField[$field] ?? []);
                $maxPoints = round($coefficient * $maxValue, 4);

                if ($maxPoints <= 0.0) {
                    return null;
                }

                return [
                    'field' => $field,
                    'label' => self::label($field, $semester, $metadataByField[$field] ?? []),
                    'coefficient' => $coefficient,
                    'max_value' => $maxValue,
                    'max_points' => $maxPoints,
                ];
            })
            ->filter()
            ->sortByDesc('max_points')
            ->values()
            ->all();
    }

    private static function coefficientForField(string $formula, string $field): float
    {
        $fieldToken = preg_quote("[{$field}]", '/');
        preg_match_all(
            '/(?P<before>(?:[-+]?\s*(?:\d*\.?\d+)\s*[*\/]\s*)*)'.$fieldToken.'(?P<after>(?:\s*[*\/]\s*(?:\d*\.?\d+))*)/i',
            $formula,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        return collect($matches)
            ->map(fn (array $match): float => abs(
                self::beforeCoefficient((string) ($match['before'][0] ?? ''))
                * self::afterCoefficient((string) ($match['after'][0] ?? ''))
                * self::wrappingCoefficient($formula, (int) ($match[0][1] ?? 0), mb_strlen($match[0][0] ?? ''))
            ))
            ->sum();
    }

    private static function beforeCoefficient(string $chain): float
    {
        preg_match_all('/([-+]?\d*\.?\d+)\s*([*\/])/', $chain, $matches, PREG_SET_ORDER);

        return collect($matches)->reduce(function (float $coefficient, array $match): float {
            $number = (float) $match[1];

            return $match[2] === '*'
                ? $coefficient * $number
                : ($number !== 0.0 ? $coefficient / $number : $coefficient);
        }, 1.0);
    }

    private static function afterCoefficient(string $chain): float
    {
        preg_match_all('/([*\/])\s*([-+]?\d*\.?\d+)/', $chain, $matches, PREG_SET_ORDER);

        return collect($matches)->reduce(function (float $coefficient, array $match): float {
            $number = (float) $match[2];

            return $match[1] === '*'
                ? $coefficient * $number
                : ($number !== 0.0 ? $coefficient / $number : $coefficient);
        }, 1.0);
    }

    private static function wrappingCoefficient(string $formula, int $tokenOffset, int $tokenLength): float
    {
        $tokenEnd = $tokenOffset + max(0, $tokenLength - 1);

        return collect(self::parenthesesRanges($formula))
            ->filter(fn (array $range): bool => $range['start'] < $tokenOffset && $range['end'] > $tokenEnd)
            ->reduce(function (float $coefficient, array $range) use ($formula): float {
                return $coefficient
                    * self::trailingWrapperCoefficient($formula, $range['end'])
                    * self::leadingWrapperCoefficient($formula, $range['start']);
            }, 1.0);
    }

    /**
     * @return array<int,array{start:int,end:int}>
     */
    private static function parenthesesRanges(string $formula): array
    {
        $stack = [];
        $ranges = [];
        $length = mb_strlen($formula);

        for ($i = 0; $i < $length; $i++) {
            $char = $formula[$i];

            if ($char === '(') {
                $stack[] = $i;
            }

            if ($char === ')' && $stack !== []) {
                $ranges[] = [
                    'start' => array_pop($stack),
                    'end' => $i,
                ];
            }
        }

        return $ranges;
    }

    private static function trailingWrapperCoefficient(string $formula, int $end): float
    {
        $after = mb_substr($formula, $end + 1);

        if (! preg_match('/^\s*([*\/])\s*([-+]?\d*\.?\d+)/', $after, $match)) {
            return 1.0;
        }

        $number = (float) $match[2];

        return $match[1] === '*'
            ? $number
            : ($number !== 0.0 ? 1.0 / $number : 1.0);
    }

    private static function leadingWrapperCoefficient(string $formula, int $start): float
    {
        $before = mb_substr($formula, 0, $start);

        if (! preg_match('/([-+]?\d*\.?\d+)\s*([*\/])\s*$/', $before, $match)) {
            return 1.0;
        }

        $number = (float) $match[1];

        return $match[2] === '*'
            ? $number
            : ($number !== 0.0 ? 1.0 / $number : 1.0);
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private static function label(string $field, string $semester, array $metadata): string
    {
        $suffix = Str::after($field, $semester.'_');

        if ($suffix === 'leadership') {
            return 'Leadership';
        }

        if (Str::startsWith($suffix, 'avg_')) {
            $destinationKey = Str::after($suffix, 'avg_');
            $sourceCategory = array_flip(RedcapSourceService::DEST_CATEGORY)[$destinationKey] ?? null;

            if ($sourceCategory !== null) {
                return RedcapSourceService::CATEGORY_LABELS[$sourceCategory] ?? Str::headline($destinationKey);
            }
        }

        $label = trim((string) ($metadata['field_label'] ?? ''));

        return $label !== '' ? $label : Str::headline($suffix);
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    private static function maxValue(string $field, array $metadata): float
    {
        $max = $metadata['text_validation_max'] ?? null;

        if (is_numeric($max) && (float) $max > 0.0) {
            return (float) $max;
        }

        if (Str::contains($field, 'leadership')) {
            return 10.0;
        }

        if (Str::contains($field, '_avg_') || Str::endsWith($field, '_score')) {
            return 100.0;
        }

        return 1.0;
    }
}
