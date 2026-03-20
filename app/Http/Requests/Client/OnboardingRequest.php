<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone' => ['required', 'string', 'max:30'],
            'nationality' => ['required', 'string', 'max:100'],
            'country_of_residence' => ['required', 'string', 'max:100'],
            'visa_type_id' => ['required', 'integer', 'exists:visa_types,id'],
            'adults_count' => ['required', 'integer', 'min:1', 'max:20'],
            'children_count' => ['required', 'integer', 'min:0', 'max:20'],
            'application_start_date' => ['required', 'date', 'after_or_equal:today'],
            'job_title' => ['required', 'string', 'max:150'],
            'employment_type' => ['required', 'string', 'in:employed,self_employed,unemployed,student'],
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agreed_to_terms' => ['required', 'accepted'],
        ];
    }
}
