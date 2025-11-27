<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NhisItemMapping extends Model
{
    /** @use HasFactory<\Database\Factories\NhisItemMappingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_type',
        'item_id',
        'item_code',
        'nhis_tariff_id',
        'gdrg_tariff_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'nhis_tariff_id' => 'integer',
            'gdrg_tariff_id' => 'integer',
        ];
    }

    /**
     * Get the NHIS tariff that this mapping links to.
     */
    public function nhisTariff(): BelongsTo
    {
        return $this->belongsTo(NhisTariff::class);
    }

    /**
     * Get the G-DRG tariff that this mapping links to.
     */
    public function gdrgTariff(): BelongsTo
    {
        return $this->belongsTo(GdrgTariff::class);
    }

    /**
     * Get the mapped item (polymorphic relationship).
     * Maps item_type to the corresponding model class.
     */
    public function item(): MorphTo
    {
        return $this->morphTo('item', 'item_type', 'item_id')->withMorphMap([
            'drug' => Drug::class,
            'lab_service' => LabService::class,
            'procedure' => MinorProcedureType::class,
            'consumable' => Drug::class, // Consumables are also stored as drugs
        ]);
    }

    /**
     * Scope a query to filter by item type.
     */
    public function scopeByItemType(Builder $query, string $itemType): Builder
    {
        return $query->where('item_type', $itemType);
    }

    /**
     * Scope a query to find mapping for a specific item.
     */
    public function scopeForItem(Builder $query, string $itemType, int $itemId): Builder
    {
        return $query->where('item_type', $itemType)
            ->where('item_id', $itemId);
    }

    /**
     * Scope a query to find mapping by item code.
     */
    public function scopeByItemCode(Builder $query, string $itemType, string $itemCode): Builder
    {
        return $query->where('item_type', $itemType)
            ->where('item_code', $itemCode);
    }

    /**
     * Get the model class for a given item type.
     */
    public static function getModelClassForType(string $itemType): ?string
    {
        return match ($itemType) {
            'drug' => Drug::class,
            'lab_service' => LabService::class,
            'procedure' => MinorProcedureType::class,
            'consumable' => Drug::class,
            default => null,
        };
    }

    /**
     * Get the code field name for a given item type.
     */
    public static function getCodeFieldForType(string $itemType): string
    {
        return match ($itemType) {
            'drug', 'consumable' => 'drug_code',
            'lab_service' => 'code',
            'procedure' => 'code',
            default => 'code',
        };
    }
}
