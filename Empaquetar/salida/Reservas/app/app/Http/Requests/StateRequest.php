<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:5',
            'description' => 'required|string|max:60',
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
        if ($this->has('state')) {
            $value = $this->input('state');
            if (in_array($value, [1, 0, '1', '0', true, false, 'true', 'false'], true)) {
                $casted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $this->merge([
                    'state' => $casted
                ]);
            }
        }
    }
}
