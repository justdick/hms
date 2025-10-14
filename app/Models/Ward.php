<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    /** @use HasFactory<\Database\Factories\WardFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'total_beds',
        'available_beds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_beds' => 'integer',
            'available_beds' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(PatientAdmission::class);
    }

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAvailable($query): void
    {
        $query->where('available_beds', '>', 0);
    }

    public function updateBedCounts(): void
    {
        $totalBeds = $this->beds()->where('is_active', true)->count();
        $availableBeds = $this->beds()->where('is_active', true)->where('status', 'available')->count();

        $this->update([
            'total_beds' => $totalBeds,
            'available_beds' => $availableBeds,
        ]);
    }
}
