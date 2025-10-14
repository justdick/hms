<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingConfiguration extends Model
{
    protected $fillable = [
        'key',
        'category',
        'value',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get configuration value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)
            ->where('is_active', true)
            ->first();

        return $config ? $config->value : $default;
    }

    /**
     * Set configuration value
     */
    public static function setValue(string $key, mixed $value, string $category = 'general', ?string $description = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'category' => $category,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get all configurations by category
     */
    public static function getByCategory(string $category): array
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Scope for active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
