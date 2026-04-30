<?php

use App\Mail\EvaluationNotification;
use App\Models\AppSetting;
use App\Services\MailTemplateRenderer;

use function Pest\Laravel\get;

beforeEach(function () {
    asService();
});

it('renders a Blade-with-mail-components template through the renderer', function () {
    $template = '<x-mail::message>Hi {{ $studentRecord["first_name"] }} — score {{ $evalRecord["teaching_score"] }}</x-mail::message>';

    $html = app(MailTemplateRenderer::class)->render(
        $template,
        EvaluationNotification::sampleViewData(),
    );

    expect($html)->toContain('Hi Catherine')
        ->and($html)->toContain('83.33')
        ->and($html)->toContain('<table'); // mail::layout chrome
});

it('does not leave preview files in resources/views/emails after rendering', function () {
    $before = glob(resource_path('views/emails/email_preview_*.blade.php')) ?: [];

    app(MailTemplateRenderer::class)->render(
        '<x-mail::message>Test {{ $studentRecord["first_name"] }}</x-mail::message>',
        EvaluationNotification::sampleViewData(),
    );

    $after = glob(resource_path('views/emails/email_preview_*.blade.php')) ?: [];

    expect($after)->toEqual($before);
});

it('shows the email-template editor to admin users', function () {
    asAdmin();

    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Edit email template');
});

it('shows the email-template editor to service users', function () {
    get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('Edit email template');
});

it('lets admin users open the email-template modal', function () {
    asAdmin();

    Livewire\Livewire::test('email-template-modal')
        ->call('open')
        ->assertOk();
});

it('forbids students from opening the email-template modal', function () {
    asStudent();

    Livewire\Livewire::test('email-template-modal')
        ->call('open')
        ->assertForbidden();
});

it('uses the custom template via the renderer when one is saved', function () {
    AppSetting::set('email_template', '<x-mail::message>CUSTOM-MARKER for {{ $studentRecord["first_name"] }}</x-mail::message>');

    $mail = new EvaluationNotification(
        evalRecord: EvaluationNotification::sampleViewData()['evalRecord'],
        studentRecord: EvaluationNotification::sampleViewData()['studentRecord'],
        slotKey: 'sem1',
        slotLabel: 'Spring 2026',
        slotIndex: 1,
        aggregates: EvaluationNotification::sampleViewData()['aggregates'],
        evalCategory: 'A',
    );

    $mail->assertSeeInHtml('CUSTOM-MARKER for Catherine');
});

it('falls back to the default markdown view when no custom template is saved', function () {
    AppSetting::forget('email_template');

    $mail = new EvaluationNotification(
        evalRecord: EvaluationNotification::sampleViewData()['evalRecord'],
        studentRecord: EvaluationNotification::sampleViewData()['studentRecord'],
        slotKey: 'sem1',
        slotLabel: 'Spring 2026',
        slotIndex: 1,
        aggregates: EvaluationNotification::sampleViewData()['aggregates'],
        evalCategory: 'A',
    );

    // Phrase from resources/views/emails/evaluation.blade.php
    $mail->assertSeeInHtml('Score Breakdown');
});

it('busts the cache when AppSetting::set is called', function () {
    AppSetting::set('email_template', 'first');
    expect(AppSetting::get('email_template'))->toBe('first');

    AppSetting::set('email_template', 'second');
    expect(AppSetting::get('email_template'))->toBe('second');
});
