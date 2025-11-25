<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WardRoundProcedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_round_id',
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

    public function wardRound(): BelongsTo
    {
        return $this->belongsTo(WardRound::class);
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
