<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\OnboardingRequest;
use App\Models\VisaType;
use App\Services\Client\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboardingService)
    {
    }

    public function show(): View
    {
        $visaTypes = VisaType::active()->orderBy('name')->get(['id', 'name']);

        return view('client.onboarding.form', compact('visaTypes'));
    }

    public function store(OnboardingRequest $request): RedirectResponse
    {
        $this->onboardingService->handle($request);

        return redirect()
            ->route('client.dashboard')
            ->with('success', __('client.application_submitted_successfully'));
    }
}
