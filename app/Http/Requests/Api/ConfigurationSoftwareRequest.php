<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurationSoftwareRequest extends FormRequest
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
            'id' => 'nullable|string',
            'pin' => 'nullable|required_with:id|numeric|digits:5',
            'url' => 'nullable|string|url',
            'idpayroll' => 'nullable|string',
            'pinpayroll' => 'nullable|required_with:idpayroll|numeric|digits:5',
            'urlpayroll' => 'nullable|string|url',
            //'idsd' => 'nullable|string',
            //'pinsd' => 'nullable|required_with:idsd|numeric|digits:5',
            //'urlsd' => 'nullable|string|url',
            'ideqdocs' => 'nullable|string',
            'pineqdocs' => 'nullable|required_with:ideqdocs|numeric|digits:5',
            'urleqdocs' => 'nullable|string|url',
        ];
    }
}
