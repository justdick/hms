<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'minor_procedure_type_id',
        'procedure_code',
        'name',
        'template_text',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(MinorProcedureType::class, 'minor_procedure_type_id');
    }

    /**
     * Get the active template for a specific procedure type.
     */
    public static function getForProcedure(int $procedureTypeId): ?self
    {
        return static::where('minor_procedure_type_id', $procedureTypeId)
            ->where('is_active', true)
            ->first();
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
