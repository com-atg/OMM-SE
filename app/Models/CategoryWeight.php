<?php

namespace App\Models;

use App\Enums\WeightCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_mapping_id', 'category', 'weight'])]
class CategoryWeight extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WeightCategory::class,
            'weight' => 'decimal:2',
        ];
    }

    public function projectMapping(): BelongsTo
    {
        return $this->belongsTo(ProjectMapping::class);
    }
}
