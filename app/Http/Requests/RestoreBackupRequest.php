<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('backups.restore') || $this->user()?->hasRole('Admin');
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'You must confirm the restore operation.',
            'confirm.accepted' => 'You must confirm the restore operation to proceed.',
        ];
    }
}
