<?php

namespace App\Mail;

use App\Services\RedcapSourceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class EvaluationNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** Criteria fields with human-readable labels, keyed by eval_category. */
    public const CRITERIA = [
        'A' => [
            'small' => 'Individual / Small Group Teaching',
            'large' => 'Large Group Teaching',
            'knowledge' => 'OMM Knowledge',
            'studevals' => 'Student Evaluation (Practical Exam / Quizzing)',
            'profess' => 'Professionalism',
        ],
        'B' => [
            'effhx' => 'Takes an Effective History',
            'apphx' => 'Performs Appropriate History',
            'diffdx' => 'Generates Differential Diagnosis',
            'gentxplan' => 'Generates and Manages Treatment Plan',
            'ex_know' => 'Exhibits Knowledge of Diseases / Pathophysiology',
            'ev_base' => 'Evidence-Based Medicine Skills',
            'team' => 'Teamwork',
            'comm' => 'Communication with Patients and Families',
            'writ_com' => 'Written Communication',
            'oral' => 'Oral Presentation Skills',
            'opp' => 'Osteopathic Principles and Practice',
            'respect' => 'Respect and Compassion',
            'resp_feedback' => 'Response to Feedback',
            'account' => 'Accountability',
        ],
        'C' => [
            're_focus' => 'Research Focus',
            're_meth' => 'Research Methods',
            're_reults' => 'Research Results',
            're_concl' => 'Research Conclusions',
            're_doc' => 'Source Documentation and Quality',
            're_man_format' => 'Manuscript Format',
            're_prof' => 'Professionalism',
            're_prep' => 'Preparation',
        ],
        'D' => [
            'study_overview' => 'Study Overview',
            'study_analys' => 'Study Analysis and Critique',
            'study_concl' => 'Study Conclusions',
            'preparedness' => 'Preparedness',
            'presentation' => 'Presentation',
            'hands_on' => 'Hands-On Didactic Skills',
        ],
    ];

    /** Score scale label per eval_category. */
    public const SCORE_SCALE = [
        'A' => '1–6 (0 = N/A)',
        'B' => '1–4 (0 = Not observed)',
        'C' => '1–6 (0 = N/A)',
        'D' => '1–6 (0 = N/A)',
    ];

    public function __construct(
        public readonly array $evalRecord,
        public readonly array $scholarRecord,
        public readonly string $semester,
        public readonly array $aggregates,
        public readonly string $evalCategory,
    ) {}

    public function envelope(): Envelope
    {
        $label = RedcapSourceService::CATEGORY_LABELS[$this->evalCategory] ?? 'Scholar';

        return new Envelope(
            subject: "[OMM Scholar Eval] {$label} Evaluation",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.evaluation',
            with: [
                'criteria' => self::CRITERIA[$this->evalCategory] ?? [],
                'scoreScale' => self::SCORE_SCALE[$this->evalCategory] ?? '',
                'categoryLabel' => RedcapSourceService::CATEGORY_LABELS[$this->evalCategory] ?? '',
                'scoreField' => RedcapSourceService::SCORE_FIELDS[$this->evalCategory] ?? '',
                'currentCategoryKey' => RedcapSourceService::DEST_CATEGORY[$this->evalCategory] ?? null,
                'evalDate' => $this->formattedEvalDate(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function formattedEvalDate(): string
    {
        $rawDate = trim((string) ($this->evalRecord['date_lab'] ?? ''));

        if ($rawDate === '') {
            return 'Unknown date';
        }

        foreach (['m-d-Y', 'Y-m-d', 'm/d/Y', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, $rawDate)->toFormattedDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($rawDate)->toFormattedDateString();
        } catch (\Throwable) {
            return $rawDate;
        }
    }
}
