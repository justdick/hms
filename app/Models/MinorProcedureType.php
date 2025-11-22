<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinorProcedureType extends Model
{
    /** @use HasFactory<\Database\Factories\MinorProcedureTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'category',
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
}
