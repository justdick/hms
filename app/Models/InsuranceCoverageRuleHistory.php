<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceCoverageRuleHistory extends Model
{
    protected $table = 'insurance_coverage_rule_history';

    protected $fillable = [
        'insurance_coverage_rule_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'notes',
        'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(InsuranceCoverageRule::class, 'insurance_coverage_rule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getChangedFieldsAttribute(): array
    {
        if (! $this->old_values || ! $this->new_values) {
            return [];
        }

        $changed = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }
}
