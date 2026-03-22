<?php

namespace App\Http\Requests\Client;

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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:10240'],
            'application_task_id' => ['nullable', 'integer', 'exists:application_tasks,id'],
            'application_id' => ['required_without:application_task_id', 'nullable', 'integer', 'exists:visa_applications,id'],
        ];
    }
}
