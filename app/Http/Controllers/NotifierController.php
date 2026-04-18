<?php

namespace App\Http\Controllers;

use App\Mail\EvaluationNotification;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifierController extends Controller
{
    private const SEMESTER_MAP = ['1' => 'spring', '2' => 'fall'];

    public function __invoke(
        Request $request,
        RedcapSourceService $source,
        RedcapDestinationService $destination,
    ): Response {
        $recordId = $request->input('record');

        if (! $recordId) {
            Log::warning('NotifierController: webhook received with no record ID.');

            return response('', 200);
        }

        // 1. Fetch the triggering eval record from source.
        $evalRecord = $source->getRecord($recordId);

        if (empty($evalRecord)) {
            Log::error("NotifierController: record {$recordId} not found in source.");

            return response('', 200);
        }

        // Validate required fields before processing.
        $scholarCode = (string) ($evalRecord['student'] ?? '');
        $semesterCode = (string) ($evalRecord['semester'] ?? '');
        $evalCategory = (string) ($evalRecord['eval_category'] ?? '');

        if ($scholarCode === '' || $semesterCode === '' || $evalCategory === '') {
            Log::error("NotifierController: record {$recordId} is missing required fields.", [
                'student' => $scholarCode,
                'semester' => $semesterCode,
                'eval_category' => $evalCategory,
            ]);

            return response('', 200);
        }

        $semester = self::SEMESTER_MAP[$semesterCode] ?? null;

        if ($semester === null) {
            Log::error("NotifierController: unknown semester code '{$semesterCode}' in record {$recordId}.");

            return response('', 200);
        }

        // 2. Fetch all evals for this scholar + semester to recalculate aggregates.
        $allEvals = $source->getScholarEvals($scholarCode, $semesterCode);

        // 3. Aggregate scores per category and collect comments.
        $aggregates = $this->aggregate($allEvals, $semester);

        // 4. Find destination record by datatelid (the raw value of the source 'student' field).
        $scholarRecord = $destination->findScholarByDatatelId($scholarCode);

        if (! $scholarRecord) {
            Log::error("NotifierController: no destination record found for datatelid '{$scholarCode}'.");

            return response('', 200);
        }

        $fullName = trim(($scholarRecord['first_name'] ?? '').' '.($scholarRecord['last_name'] ?? ''));

        // 5. Push updated aggregates to destination.
        $updatePayload = array_merge(
            ['record_id' => $scholarRecord['record_id']],
            $aggregates['fields'],
        );

        $destination->updateScholarRecord($updatePayload);

        Log::info("NotifierController: updated destination record {$scholarRecord['record_id']} for {$fullName}.");

        // 6. Send email notification — email and name come from the destination scholar record.
        $scholarEmail = filter_var($scholarRecord['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
        $facultyEmail = filter_var($evalRecord['faculty_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;

        if ($scholarEmail) {
            $mailable = new EvaluationNotification(
                evalRecord: $evalRecord,
                scholarRecord: $scholarRecord,
                semester: $semester,
                aggregates: $aggregates,
                evalCategory: $evalCategory,
            );

            $mailer = Mail::to($scholarEmail);

            if ($facultyEmail) {
                $mailer->cc($facultyEmail);
            }

            $mailer->bcc(config('mail.from.address'))->send($mailable);

            Log::info("NotifierController: email sent to {$scholarEmail}.");
        }

        return response('', 200);
    }

    /**
     * Aggregate all evals into per-category count + average + comments.
     *
     * @return array{fields: array<string,mixed>, by_category: array<string,array{nu:int,avg:float|null}>, semester: string}
     */
    private function aggregate(array $evals, string $semester): array
    {
        $sums = [];
        $counts = [];
        $commentsList = [];

        foreach ($evals as $eval) {
            $category = $eval['eval_category'] ?? '';
            $destKey = RedcapSourceService::DEST_CATEGORY[$category] ?? null;
            $scoreField = RedcapSourceService::SCORE_FIELDS[$category] ?? null;

            if ($destKey && $scoreField && isset($eval[$scoreField]) && $eval[$scoreField] !== '') {
                $score = (float) $eval[$scoreField];

                if ($score < 0.0 || $score > 100.0) {
                    Log::warning("NotifierController: score {$score} out of range for {$scoreField}, skipping.");

                    continue;
                }

                $sums[$destKey] = ($sums[$destKey] ?? 0.0) + $score;
                $counts[$destKey] = ($counts[$destKey] ?? 0) + 1;
            }

            if (! empty($eval['comments'])) {
                $faculty = $eval['faculty'] ?? 'Faculty';
                $commentsList[] = "[{$faculty}]: {$eval['comments']}";
            }
        }

        $fields = [];
        $byCategory = [];

        foreach (RedcapSourceService::DEST_CATEGORY as $destKey) {
            $nu = $counts[$destKey] ?? 0;
            $avg = $nu > 0 ? round($sums[$destKey] / $nu, 2) : null;

            $fields["{$semester}_nu_{$destKey}"] = $nu;
            if ($avg !== null) {
                $fields["{$semester}_avg_{$destKey}"] = $avg;
            }

            $byCategory[$destKey] = ['nu' => $nu, 'avg' => $avg];
        }

        $commentCount = count($commentsList);
        $fields["{$semester}_nu_comments"] = $commentCount;
        if ($commentCount > 0) {
            $fields["{$semester}_comments"] = implode("\n\n", $commentsList);
        }

        return [
            'fields' => $fields,
            'by_category' => $byCategory,
            'semester' => $semester,
        ];
    }
}
