<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'diagnosis',
        'code',
        'g_drg',
        'icd_10',
    ];

    public function consultationDiagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class);
    }

    public function insuranceClaimDiagnoses(): HasMany
    {
        return $this->hasMany(InsuranceClaimDiagnosis::class);
    }
}
