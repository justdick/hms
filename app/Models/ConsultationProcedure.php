<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationProcedure extends Model
{
    /** @use HasFactory<\Database\Factories\ConsultationProcedureFactory> */
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'doctor_id',
        'minor_procedure_type_id',
        'comments',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function procedureType(): BelongsTo
    {
        return $this->belongsTo(MinorProcedureType::class, 'minor_procedure_type_id');
    }
}
