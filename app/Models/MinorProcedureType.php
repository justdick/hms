<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MinorProcedureType extends Model
{
    /** @use HasFactory<\Database\Factories\MinorProcedureTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
        'type',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function minorProcedures(): HasMany
    {
        return $this->hasMany(MinorProcedure::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(MinorProcedure::class);
    }

    /**
     * Get the NHIS item mapping for this procedure type.
     */
    public function nhisMapping(): HasOne
    {
        return $this->hasOne(NhisItemMapping::class, 'item_id')
            ->where('item_type', 'procedure');
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

    public function scopeByType($query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeMinor($query): void
    {
        $query->where('type', 'minor');
    }

    public function scopeMajor($query): void
    {
        $query->where('type', 'major');
    }
}
