<?php

use App\Mail\EvaluationNotification;
use App\Models\AppSetting;
use App\Services\MailTemplateRenderer;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $template = '';

    public string $activeTab = 'edit';

    public string $previewHtml = '';

    public string $previewError = '';

    #[On('open-email-template')]
    public function open(): void
    {
        $this->authorize('edit-email-template');

        $this->template = AppSetting::get('email_template')
            ?? file_get_contents(resource_path('views/emails/evaluation.blade.php'));
        $this->activeTab = 'edit';
        $this->previewHtml = '';
        $this->previewError = '';
        $this->modal('email-template')->show();
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'preview') {
            $this->renderPreview();
        }
    }

    public function renderPreview(): void
    {
        $this->authorize('edit-email-template');

        $this->previewHtml = '';
        $this->previewError = '';

        try {
            $this->previewHtml = app(MailTemplateRenderer::class)->render(
                $this->template,
                EvaluationNotification::sampleViewData(),
            );
        } catch (\Throwable $e) {
            $this->previewError = $e->getMessage();
        }
    }

    public function save(): void
    {
        $this->authorize('edit-email-template');

        AppSetting::set('email_template', $this->template);

        $this->modal('email-template')->close();
        $this->dispatch('email-template-saved');
    }

    public function restoreDefault(): void
    {
        $this->authorize('edit-email-template');

        $this->template = file_get_contents(resource_path('views/emails/evaluation.blade.php'));
        AppSetting::set('email_template', $this->template);

        $this->previewHtml = '';
        $this->previewError = '';
        $this->activeTab = 'edit';

        $this->dispatch('email-template-saved');
        $this->modal('email-template')->close();
    }
}
?>

<flux:modal name="email-template" class="w-full max-w-4xl">
    <div class="flex h-[80vh] flex-col gap-0">
        {{-- Header --}}
        <div class="flex shrink-0 items-start justify-between gap-4 px-6 pt-6 pb-4">
            <div>
                <flux:heading size="lg">Email Template</flux:heading>
                <flux:subheading>Edit the evaluation notification email. Use Blade syntax for dynamic content.</flux:subheading>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="shrink-0 border-b border-slate-200 px-6">
            <div class="flex gap-0">
                <button
                    wire:click="switchTab('edit')"
                    class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $activeTab === 'edit' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                >
                    Edit
                </button>
                <button
                    wire:click="switchTab('preview')"
                    class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $activeTab === 'preview' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-700' }}"
                >
                    Preview
                    <span class="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[0.65rem] font-semibold tracking-wide text-slate-500">sample data</span>
                </button>
            </div>
        </div>

        {{-- Tab content --}}
        <div class="min-h-0 flex-1 overflow-hidden">
            @if ($activeTab === 'edit')
                <textarea
                    wire:model="template"
                    spellcheck="false"
                    class="h-full w-full resize-none bg-slate-950 p-5 font-mono text-xs leading-relaxed text-slate-200 outline-none"
                    placeholder="Blade template content…"
                >{{ $template }}</textarea>
            @else
                <div class="h-full overflow-y-auto bg-slate-100 p-5">
                    @if ($previewError)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-red-700">Blade error — fix the template and re-preview</p>
                            <pre class="whitespace-pre-wrap font-mono text-xs text-red-700">{{ $previewError }}</pre>
                        </div>
                    @elseif ($previewHtml)
                        {{-- iframe srcdoc isolates the preview from the host page (no JS, no CSS bleed). --}}
                        <iframe
                            wire:key="preview-{{ md5($previewHtml) }}"
                            sandbox=""
                            srcdoc="{{ $previewHtml }}"
                            class="h-full min-h-[60vh] w-full rounded-lg border border-slate-200 bg-white"
                            title="Email preview"
                        ></iframe>
                    @else
                        <div class="flex h-full items-center justify-center text-sm text-slate-400">
                            Loading preview…
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="flex shrink-0 items-center justify-between border-t border-slate-200 px-6 py-4">
            <flux:button
                wire:click="restoreDefault"
                wire:confirm="Reset to the default template? Your current edits will be lost."
                variant="ghost"
                icon="arrow-path"
            >Reset to default</flux:button>

            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="save" variant="primary" icon="check">Save template</flux:button>
            </div>
        </div>
    </div>
</flux:modal>
