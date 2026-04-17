<x-mail::message>
# {{ $categoryLabel }} Evaluation — {{ \Carbon\Carbon::createFromFormat('m-d-Y', $evalRecord['date_lab'])->toFormattedDateString() }}
## Semester: {{ ucfirst($semester) }}

Dear {{ $scholarRecord['goes_by'] !== '' ? $scholarRecord['goes_by'] : $scholarRecord['first_name'] }},

Your **{{ $categoryLabel }}** evaluation has been recorded. The details are below.

---

<x-mail::table>
| Criterion | Score ({{ $scoreScale }}) |
| :--- | :---: |
@foreach ($criteria as $field => $label)
@php $val = $evalRecord[$field] ?? '—'; @endphp
| {{ $label }} | {{ $val == '0' ? 'N/A' : $val }} |
@endforeach
</x-mail::table>

### Overall {{ $categoryLabel }} Score: **{{ $evalRecord[$scoreField] ?? '—' }}%**

---

@if (!empty($evalRecord['comments']))
## Faculty Feedback

<x-mail::panel>
{{ $evalRecord['comments'] }}
</x-mail::panel>

@endif

## Your {{ ucfirst($semester) }} Semester Summary

*As of {{ \Carbon\Carbon::now()->toFormattedDateString() }}*

<x-mail::table>
| Category | Weight | # Evaluations | Average Score |
| :--- | :---: | :---: | :---: |
| Teaching | 25% | {{ $aggregates['by_category']['teaching']['nu'] }} | {{ $aggregates['by_category']['teaching']['avg'] !== null ? $aggregates['by_category']['teaching']['avg'] . '%' : '—' }} |
| Clinic | 25% | {{ $aggregates['by_category']['clinic']['nu'] }} | {{ $aggregates['by_category']['clinic']['avg'] !== null ? $aggregates['by_category']['clinic']['avg'] . '%' : '—' }} |
| Research | 25% | {{ $aggregates['by_category']['research']['nu'] }} | {{ $aggregates['by_category']['research']['avg'] !== null ? $aggregates['by_category']['research']['avg'] . '%' : '—' }} |
| Didactics | 25% | {{ $aggregates['by_category']['didactics']['nu'] }} | {{ $aggregates['by_category']['didactics']['avg'] !== null ? $aggregates['by_category']['didactics']['avg'] . '%' : '—' }} |
</x-mail::table>

---

Thank you,<br>
**Department of OMM**
</x-mail::message>
