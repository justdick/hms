<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfiguration extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'group',
    ];

    /**
     * Get a configuration value by key with caching
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("system_config:{$key}", 3600, function () use ($key, $default) {
            $config = self::where('key', $key)->first();

            if (! $config) {
                return $default;
            }

            return self::castValue($config->value, $config->type);
        });
    }

    /**
     * Set a configuration value
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null, string $group = 'general'): void
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
                'group' => $group,
            ]
        );

        Cache::forget("system_config:{$key}");
    }

    /**
     * Get all configurations for a group
     */
    public static function getGroup(string $group): array
    {
        return Cache::remember("system_config_group:{$group}", 3600, function () use ($group) {
            return self::where('group', $group)
                ->get()
                ->mapWithKeys(function ($config) {
                    return [$config->key => self::castValue($config->value, $config->type)];
                })
                ->toArray();
        });
    }

    /**
     * Clear configuration cache
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget("system_config:{$key}");
        } else {
            Cache::flush();
        }
    }

    /**
     * Cast value based on type
     */
    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Scope to filter by group
     */
    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
