<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers'   => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:5000'],
        ];
    }
}
