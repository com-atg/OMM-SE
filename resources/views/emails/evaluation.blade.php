@php
    $firstName = $studentRecord['goes_by'] !== '' ? $studentRecord['goes_by'] : $studentRecord['first_name'];
    $facultyName = trim((string) ($evalRecord['faculty'] ?? '')) !== '' ? $evalRecord['faculty'] : 'Faculty';
    $overallScore = $evalRecord[$scoreField] ?? null;

    $categoryRows = [
        'teaching' => 'Teaching',
        'clinic' => 'Clinic',
        'research' => 'Research',
        'didactics' => 'Didactics',
    ];
@endphp

<x-mail::message>
# {{ $categoryLabel }} Evaluation

<p style="color:#71717a;font-size:14px;margin-top:-8px;">{{ $evalDate }} &nbsp;&middot;&nbsp; {{ ucfirst($semester) }} Semester &nbsp;&middot;&nbsp; {{ $facultyName }}</p>

Hi {{ $firstName }},

A new **{{ $categoryLabel }}** evaluation has just been added to your record. Here's a quick look.

<x-mail::panel>
<p style="margin:0;font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:#0f766e;">Overall {{ $categoryLabel }} Score</p>
<p style="margin:6px 0 0;font-size:36px;font-weight:700;color:#134e4a;line-height:1;">{{ $overallScore !== null && $overallScore !== '' ? $overallScore.'%' : '—' }}</p>
</x-mail::panel>

## Score Breakdown

<p style="color:#71717a;font-size:13px;margin-top:-12px;">Scale: {{ $scoreScale }}</p>

<x-mail::table>
| Criterion | Score |
| :--- | ---: |
@foreach ($criteria as $field => $label)
@php $val = $evalRecord[$field] ?? '—'; @endphp
| {{ $label }} | {{ $val === '0' ? 'N/A' : $val }} |
@endforeach
</x-mail::table>

@if (! empty($evalRecord['comments']))
## Faculty Feedback

<p style="color:#71717a;font-size:13px;margin-top:-12px;">From {{ $facultyName }}</p>

<x-mail::panel>
{{ $evalRecord['comments'] }}
</x-mail::panel>
@endif

## {{ ucfirst($semester) }} Semester Progress

<p style="color:#71717a;font-size:13px;margin-top:-12px;">As of {{ now()->toFormattedDateString() }} &nbsp;&middot;&nbsp; Each category contributes 25% to your semester score.</p>

<x-mail::table>
| Category | Evaluations | Average |
| :--- | :---: | ---: |
@foreach ($categoryRows as $key => $label)
@php
    $data = $aggregates['by_category'][$key] ?? ['nu' => 0, 'avg' => null];
    $isCurrent = ($currentCategoryKey ?? null) === $key;
    $avgDisplay = $data['avg'] !== null ? $data['avg'].'%' : '—';
    $labelDisplay = $isCurrent ? "**{$label}**" : $label;
    $nuDisplay = $isCurrent ? "**{$data['nu']}**" : (string) $data['nu'];
    $avgDisplay = $isCurrent ? "**{$avgDisplay}**" : $avgDisplay;
@endphp
| {{ $labelDisplay }} | {{ $nuDisplay }} | {{ $avgDisplay }} |
@endforeach
</x-mail::table>

Keep up the great work — every evaluation is a step in your growth.

Warm regards,<br>
**Department of OMM**

<x-mail::subcopy>
Questions about this evaluation? Reply to this email, or reach out to the OMM Department directly. This is an automated notification from {{ config('app.name') }}.
</x-mail::subcopy>
</x-mail::message>
