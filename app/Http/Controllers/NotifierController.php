<?php

namespace App\Http\Controllers;

use App\Mail\EvaluationNotification;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifierController extends Controller
{
    private const SEMESTER_MAP = EvalAggregator::SEMESTER_MAP;

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

        $sourceToken = $this->sourceTokenFor($request);

        // 1. Fetch the triggering eval record from source.
        $evalRecord = $source->getRecord($recordId, $sourceToken);

        if (empty($evalRecord)) {
            Log::error("NotifierController: record {$recordId} not found in source.");

            return response('', 200);
        }

        // Validate required fields before processing.
        $studentCode = (string) ($evalRecord['student'] ?? '');
        $semesterCode = (string) ($evalRecord['semester'] ?? '');
        $evalCategory = (string) ($evalRecord['eval_category'] ?? '');

        if ($studentCode === '' || $semesterCode === '' || $evalCategory === '') {
            Log::error("NotifierController: record {$recordId} is missing required fields.", [
                'student' => $studentCode,
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

        // 2. Fetch all evals for this student + semester to recalculate aggregates.
        $allEvals = $source->getStudentEvals($studentCode, $semesterCode, $sourceToken);

        // 3. Aggregate scores per category and collect comments.
        $aggregates = EvalAggregator::aggregate($allEvals, $semester);

        // 4. Find destination record by datatelid (the raw value of the source 'student' field).
        $studentRecord = $destination->findStudentByDatatelId($studentCode);

        if (! $studentRecord) {
            Log::error("NotifierController: no destination record found for datatelid '{$studentCode}'.");

            return response('', 200);
        }

        $fullName = trim(($studentRecord['first_name'] ?? '').' '.($studentRecord['last_name'] ?? ''));

        // 5. Push updated aggregates to destination.
        $updatePayload = array_merge(
            ['record_id' => $studentRecord['record_id']],
            $aggregates['fields'],
        );

        $destination->updateStudentRecord($updatePayload);

        Log::info("NotifierController: updated destination record {$studentRecord['record_id']} for {$fullName}.");

        // 6. Send email notification — email and name come from the destination student record.
        $studentEmail = filter_var($studentRecord['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
        $facultyEmail = filter_var($evalRecord['faculty_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;

        if ($studentEmail) {
            $mailable = new EvaluationNotification(
                evalRecord: $evalRecord,
                studentRecord: $studentRecord,
                semester: $semester,
                aggregates: $aggregates,
                evalCategory: $evalCategory,
            );

            $mailer = Mail::to($studentEmail);

            if ($facultyEmail) {
                $mailer->cc($facultyEmail);
            }

            $mailer->bcc(config('mail.from.address'))->send($mailable);

            Log::info("NotifierController: email sent to {$studentEmail}.");
        }

        return response('', 200);
    }

    private function sourceTokenFor(Request $request): ?string
    {
        $projectId = trim((string) $request->input('project_id', ''));

        if ($projectId === '') {
            return null;
        }

        $projectMapping = ProjectMapping::query()
            ->where('redcap_pid', $projectId)
            ->first();

        if ($projectMapping) {
            return (string) $projectMapping->redcap_token;
        }

        $projectToken = config("redcap.project_tokens.{$projectId}");

        if (is_string($projectToken) && $projectToken !== '') {
            return $projectToken;
        }

        Log::warning('NotifierController: no source token mapping found for webhook project_id; falling back to REDCAP_SOURCE_TOKEN.', [
            'project_id' => $projectId,
        ]);

        return null;
    }
}
