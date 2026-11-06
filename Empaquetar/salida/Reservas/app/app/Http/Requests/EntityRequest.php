<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:60',
            'abbr' => 'required|string|max:10',
            'logo' => 'nullable|string',
        ];
    }
}
