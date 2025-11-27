<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GdrgTariff extends Model
{
    /** @use HasFactory<\Database\Factories\GdrgTariffFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'mdc_category',
        'tariff_price',
        'age_category',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tariff_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the display name formatted as "Name (Code - GHS Price)".
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf(
            '%s (%s - GHS %.2f)',
            $this->name,
            $this->code,
            $this->tariff_price
        );
    }

    /**
     * Scope a query to only include active tariffs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search tariffs by code or name.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('mdc_category', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter tariffs by MDC category.
     */
    public function scopeByMdcCategory(Builder $query, ?string $mdcCategory): Builder
    {
        if (empty($mdcCategory)) {
            return $query;
        }

        return $query->where('mdc_category', $mdcCategory);
    }

    /**
     * Scope a query to filter tariffs by age category.
     */
    public function scopeByAgeCategory(Builder $query, ?string $ageCategory): Builder
    {
        if (empty($ageCategory)) {
            return $query;
        }

        return $query->where('age_category', $ageCategory);
    }

    /**
     * Get the insurance claims that use this G-DRG tariff.
     */
    public function insuranceClaims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }
}
