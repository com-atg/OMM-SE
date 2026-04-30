<?php

namespace App\Http\Controllers;

use App\Mail\EvaluationNotification;
use App\Models\ProjectMapping;
use App\Services\RedcapDestinationService;
use App\Services\RedcapSourceService;
use App\Support\EvalAggregator;
use App\Support\SemesterSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifierController extends Controller
{
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

        $sourceToken = $this->sourceToken();

        if ($sourceToken === null) {
            return response('', 200);
        }

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
        $dateLab = (string) ($evalRecord['date_lab'] ?? '');

        if ($studentCode === '' || $semesterCode === '' || $evalCategory === '') {
            Log::error("NotifierController: record {$recordId} is missing required fields.", [
                'has_student' => $studentCode !== '',
                'has_semester' => $semesterCode !== '',
                'has_eval_category' => $evalCategory !== '',
            ]);

            return response('', 200);
        }

        if (! isset(SemesterSlot::SOURCE_SEMESTER_TERM[$semesterCode])) {
            Log::error("NotifierController: unknown semester code '{$semesterCode}' in record {$recordId}.");

            return response('', 200);
        }

        $evalYear = SemesterSlot::yearFromDate($dateLab);

        if ($evalYear === null) {
            Log::error("NotifierController: record {$recordId} has missing or unparseable date_lab '{$dateLab}'.");

            return response('', 200);
        }

        // 2. Find destination record by datatelid (the raw value of the source 'student' field).
        $studentRecord = $destination->findStudentByDatatelId($studentCode);

        if (! $studentRecord) {
            Log::error("NotifierController: no destination record found for source record {$recordId}.");

            return response('', 200);
        }

        // 3. Compute slot 1–4 from cohort start. Skip if eval falls outside the window.
        $cohortTerm = trim((string) ($studentRecord['cohort_start_term'] ?? '')) ?: null;
        $cohortYearRaw = trim((string) ($studentRecord['cohort_start_year'] ?? ''));
        $cohortYear = $cohortYearRaw !== '' && ctype_digit($cohortYearRaw) ? (int) $cohortYearRaw : null;

        $slot = SemesterSlot::compute($semesterCode, $dateLab, $cohortTerm, $cohortYear);

        if ($slot === null) {
            Log::warning('NotifierController: eval falls outside scholar 4-semester window; skipping aggregation.', [
                'record' => $recordId,
                'semester' => $semesterCode,
                'date_lab' => $dateLab,
                'cohort_term' => $cohortTerm,
                'cohort_year' => $cohortYear,
            ]);

            return response('', 200);
        }

        $slotKey = SemesterSlot::slotKey($slot);

        // 4. Fetch all evals for this student + semester + year to recalculate aggregates.
        $allEvals = $source->getStudentEvals($studentCode, $semesterCode, $evalYear, $sourceToken);

        // 5. Aggregate scores per category and collect comments.
        $aggregates = EvalAggregator::aggregate($allEvals, $slotKey);

        // 6. Push updated aggregates to destination.
        $updatePayload = array_merge(
            ['record_id' => $studentRecord['record_id']],
            $aggregates['fields'],
        );

        $destination->updateStudentRecord($updatePayload);

        Log::info("NotifierController: updated destination record {$studentRecord['record_id']} (slot {$slot}).");

        // 7. Send email notification — email and name come from the destination student record.
        $studentEmail = filter_var($studentRecord['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
        $facultyEmail = filter_var($evalRecord['faculty_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;

        if ($studentEmail) {
            $slotLabel = SemesterSlot::labelsFor($cohortTerm, $cohortYear)[$slot] ?? "Semester {$slot}";

            $mailable = new EvaluationNotification(
                evalRecord: $evalRecord,
                studentRecord: $studentRecord,
                slotKey: $slotKey,
                slotLabel: $slotLabel,
                slotIndex: $slot,
                aggregates: $aggregates,
                evalCategory: $evalCategory,
            );

            $mailer = Mail::to($studentEmail);

            if ($facultyEmail) {
                $mailer->cc($facultyEmail);
            }

            $mailer->bcc(config('mail.from.address'))->send($mailable);

            Log::info("NotifierController: email sent for destination record {$studentRecord['record_id']}.");
        }

        return response('', 200);
    }

    private function sourceToken(): ?string
    {
        $mapping = ProjectMapping::activeSource();

        if ($mapping === null) {
            Log::error('NotifierController: no active source project mapping configured.');

            return null;
        }

        return (string) $mapping->redcap_token;
    }
}
