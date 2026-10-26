<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OriginRequest extends FormRequest
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
            'code' => 'required|string|max:5',
            'description' => 'required|string|max:60',
            'state' => 'required|boolean',
        ];
    }

     protected function prepareForValidation()
    {
        if ($this->has('state')) {
            $this->merge([
                'state' => filter_var($this->state, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            ]);
        }
    }
}
