<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhisSettings extends Model
{
    protected $fillable = [
        'verification_mode',
        'nhia_portal_url',
        'facility_code',
        'nhia_username',
        'nhia_password',
        'auto_open_portal',
    ];

    protected function casts(): array
    {
        return [
            'auto_open_portal' => 'boolean',
            'nhia_password' => 'encrypted',
        ];
    }

    /**
     * Get the singleton instance of NHIS settings.
     */
    public static function getInstance(): self
    {
        $settings = self::first();

        if (! $settings) {
            $settings = self::create([
                'verification_mode' => 'manual',
                'nhia_portal_url' => 'https://ccc.nhia.gov.gh/',
                'auto_open_portal' => true,
            ]);
        }

        return $settings;
    }

    public function isExtensionMode(): bool
    {
        return $this->verification_mode === 'extension';
    }

    public function isManualMode(): bool
    {
        return $this->verification_mode === 'manual';
    }
}
