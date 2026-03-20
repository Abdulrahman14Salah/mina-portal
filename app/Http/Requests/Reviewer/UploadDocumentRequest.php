<?php

namespace App\Http\Requests\Reviewer;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'application_task_id' => ['required', 'integer', 'exists:application_tasks,id'],
        ];
    }
}
