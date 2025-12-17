<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabService extends Model
{
    /** @use HasFactory<\Database\Factories\LabServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'preparation_instructions',
        'price',
        'sample_type',
        'turnaround_time',
        'normal_range',
        'clinical_significance',
        'test_parameters',
        'is_active',
        'is_imaging',
        'modality',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_imaging' => 'boolean',
            'test_parameters' => 'array',
        ];
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    /**
     * Get the NHIS item mapping for this lab service.
     */
    public function nhisMapping(): HasOne
    {
        return $this->hasOne(NhisItemMapping::class, 'item_id')
            ->where('item_type', 'lab_service');
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category): void
    {
        $query->where('category', $category);
    }

    public function scopeSearch($query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
                ->orWhere('code', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    public function scopeImaging($query): void
    {
        $query->where('is_imaging', true);
    }

    public function scopeLaboratory($query): void
    {
        $query->where('is_imaging', false);
    }
}
