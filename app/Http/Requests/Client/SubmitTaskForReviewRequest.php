<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskForReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $this->authorize()
    }

    public function rules(): array
    {
        return []; // No body required — the action itself is the intent
    }
}
