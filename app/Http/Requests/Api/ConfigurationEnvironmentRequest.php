<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurationEnvironmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'type_environment_id' => 'nullable|exists:type_environments,id',
            'payroll_type_environment_id' => 'nullable|exists:type_environments,id',
            'eqdocs_type_environment_id' => 'nullable|exists:type_environments,id',
        ];
    }
}
