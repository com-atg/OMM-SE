<?php

use App\Enums\WeightCategory;
use App\Models\CategoryWeight;
use App\Models\ProjectMapping;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public ?int $projectMappingId = null;

    public string $academicYear = '';

    /** @var array<string, string> */
    public array $weights = [
        'teaching' => '',
        'clinic' => '',
        'research' => '',
        'didactics' => '',
        'leadership' => '',
    ];

    #[On('open-weights')]
    public function open(int $id): void
    {
        $mapping = ProjectMapping::findOrFail($id);
        $this->projectMappingId = $id;
        $this->academicYear = $mapping->academic_year;

        $existing = $mapping->categoryWeights()->get()->keyBy(fn (CategoryWeight $w) => $w->category->value);

        foreach (array_keys($this->weights) as $category) {
            $this->weights[$category] = $existing->has($category)
                ? (string) $existing->get($category)->weight
                : '';
        }

        $this->resetValidation();
        $this->modal('edit-weights')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'weights.teaching' => ['required', 'numeric', 'min:0', 'max:100'],
            'weights.clinic' => ['required', 'numeric', 'min:0', 'max:100'],
            'weights.research' => ['required', 'numeric', 'min:0', 'max:100'],
            'weights.didactics' => ['required', 'numeric', 'min:0', 'max:100'],
            'weights.leadership' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'weights.teaching.required' => 'Teaching weight is required.',
            'weights.clinic.required' => 'Clinic weight is required.',
            'weights.research.required' => 'Research weight is required.',
            'weights.didactics.required' => 'Didactics weight is required.',
            'weights.leadership.required' => 'Leadership weight is required.',
        ]);

        $total = array_sum(array_map('floatval', $validated['weights']));

        if (round($total, 2) !== 100.0) {
            $this->addError('weights.total', 'Weights must sum to 100%. Current total: '.number_format($total, 1).'%.');

            return;
        }

        foreach (WeightCategory::cases() as $category) {
            CategoryWeight::updateOrCreate(
                ['project_mapping_id' => $this->projectMappingId, 'category' => $category->value],
                ['weight' => $validated['weights'][$category->value]],
            );
        }

        $this->modal('edit-weights')->close();
        $this->dispatch('weights-saved');
    }
}
?>

<flux:modal name="edit-weights" class="w-full max-w-md">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Edit Category Weights</flux:heading>
            <flux:subheading>Configure score weights for {{ $academicYear }}. Values must sum to 100%.</flux:subheading>
        </div>

        <div class="space-y-4">
            @foreach (\App\Enums\WeightCategory::cases() as $category)
                <div>
                    <flux:input
                        wire:model="weights.{{ $category->value }}"
                        label="{{ $category->label() }}"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        suffix="%"
                        placeholder="0"
                    />
                    @error('weights.' . $category->value)
                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            @error('weights.total')
                <flux:callout icon="exclamation-triangle" color="red">
                    <flux:callout.text>{{ $message }}</flux:callout.text>
                </flux:callout>
            @enderror

            @php
                $total = collect($weights)->sum(fn($v) => is_numeric($v) ? (float) $v : 0);
            @endphp

            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2.5 text-sm">
                <span class="font-medium text-slate-600">Total</span>
                <span class="font-bold {{ abs($total - 100) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ number_format($total, 1) }}%
                </span>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="save" variant="primary" icon="check">Save weights</flux:button>
        </div>
    </div>
</flux:modal>
