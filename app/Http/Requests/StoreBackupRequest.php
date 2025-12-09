<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('backups.create') || $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            // No input required for manual backup creation
        ];
    }
}
