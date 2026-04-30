<?php

namespace App\Mail;

use App\Models\AppSetting;
use App\Services\MailTemplateRenderer;
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
        public readonly array $studentRecord,
        public readonly string $slotKey,
        public readonly string $slotLabel,
        public readonly int $slotIndex,
        public readonly array $aggregates,
        public readonly string $evalCategory,
    ) {}

    public function envelope(): Envelope
    {
        $label = RedcapSourceService::CATEGORY_LABELS[$this->evalCategory] ?? 'Student';

        return new Envelope(
            subject: "[OMM Student Eval] {$label} Evaluation",
        );
    }

    public function content(): Content
    {
        $viewData = [
            'evalRecord' => $this->evalRecord,
            'studentRecord' => $this->studentRecord,
            'slotKey' => $this->slotKey,
            'slotLabel' => $this->slotLabel,
            'slotIndex' => $this->slotIndex,
            'semester' => $this->slotLabel,
            'aggregates' => $this->aggregates,
            'criteria' => self::CRITERIA[$this->evalCategory] ?? [],
            'scoreScale' => self::SCORE_SCALE[$this->evalCategory] ?? '',
            'categoryLabel' => RedcapSourceService::CATEGORY_LABELS[$this->evalCategory] ?? '',
            'scoreField' => RedcapSourceService::SCORE_FIELDS[$this->evalCategory] ?? '',
            'currentCategoryKey' => RedcapSourceService::DEST_CATEGORY[$this->evalCategory] ?? null,
            'evalDate' => $this->formattedEvalDate(),
        ];

        $customTemplate = AppSetting::get('email_template');

        if ($customTemplate !== null && $customTemplate !== '') {
            return new Content(
                htmlString: app(MailTemplateRenderer::class)->render($customTemplate, $viewData),
            );
        }

        return new Content(markdown: 'emails.evaluation', with: $viewData);
    }

    public function attachments(): array
    {
        return [];
    }

    /**
     * Sample data for previewing the email template in the admin UI.
     *
     * @return array<string, mixed>
     */
    public static function sampleViewData(): array
    {
        return [
            'evalRecord' => array_merge(
                array_fill_keys(['small', 'large', 'knowledge', 'studevals', 'profess'], '4'),
                [
                    'record_id' => '1',
                    'date_lab' => '04-16-2026',
                    'semester' => '1',
                    'student' => '1',
                    'eval_category' => 'A',
                    'teaching_score' => '83.33',
                    'comments' => 'Great enthusiasm during the small group session. Keep up the excellent work!',
                    'faculty' => 'Dr. Smith',
                    'faculty_email' => 'faculty@example.com',
                ]
            ),
            'studentRecord' => [
                'record_id' => '1',
                'first_name' => 'Catherine',
                'last_name' => 'Chin',
                'goes_by' => 'Cat',
                'email' => 'catherine@example.com',
                'cohort_start_term' => 'Spring',
                'cohort_start_year' => 2026,
            ],
            'slotKey' => 'sem1',
            'slotLabel' => 'Spring 2026',
            'slotIndex' => 1,
            'semester' => 'Spring 2026',
            'aggregates' => [
                'slot_key' => 'sem1',
                'by_category' => [
                    'teaching' => ['nu' => 1, 'avg' => 83.33],
                    'clinic' => ['nu' => 0, 'avg' => null],
                    'research' => ['nu' => 0, 'avg' => null],
                    'didactics' => ['nu' => 0, 'avg' => null],
                ],
                'fields' => [],
            ],
            'criteria' => self::CRITERIA['A'],
            'scoreScale' => self::SCORE_SCALE['A'],
            'categoryLabel' => 'Teaching',
            'scoreField' => 'teaching_score',
            'currentCategoryKey' => 'teaching',
            'evalDate' => 'Apr 16, 2026',
        ];
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
