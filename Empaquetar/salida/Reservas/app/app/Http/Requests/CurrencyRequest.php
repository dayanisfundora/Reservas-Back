<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurrencyRequest extends FormRequest
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
            'abbr' => 'required|string|max:10',
            'rate' => 'required|string|max:100',
            'base_currency' => 'required|boolean',
            'state' => 'required|boolean',
        ];
    }

     /**
     * Prepare the data for validation.
     * Only casts to boolean if the value is 1, 0, '1', '0', true, or false.
     * If it is an invalid string, let the validation fail.
     */
    protected function prepareForValidation()
    {
        // Cast 'state' to boolean if valid
        if ($this->has('state')) {
            $value = $this->input('state');
            if (in_array($value, [1, 0, '1', '0', true, false, 'true', 'false'], true)) {
                $casted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $this->merge([
                    'state' => $casted
                ]);
            }
        }

        // Cast 'base_currency' to boolean if valid
        if ($this->has('base_currency')) {
            $value = $this->input('base_currency');
            if (in_array($value, [1, 0, '1', '0', true, false, 'true', 'false'], true)) {
                $casted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $this->merge([
                    'base_currency' => $casted
                ]);
            }
        }
    }
}
