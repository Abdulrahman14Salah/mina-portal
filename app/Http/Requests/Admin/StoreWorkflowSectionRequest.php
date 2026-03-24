<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visa_type_id' => ['required', 'integer', 'exists:visa_types,id'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
