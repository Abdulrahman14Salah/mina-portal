<?php

namespace App\Services\Client;

use App\Http\Requests\Client\OnboardingRequest;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Auth\AuditLogService;
use App\Services\Payments\PaymentService;
use App\Services\Tasks\WorkflowService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OnboardingService
{
    public function __construct(
        private AuditLogService $auditLog,
        private WorkflowService $workflowService,
        private PaymentService $paymentService,
    ) {}

    public function handle(OnboardingRequest $request): VisaApplication
    {
        $application = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);

            $user->assignRole('client');

            $application = VisaApplication::create([
                'user_id' => $user->id,
                'visa_type_id' => $request->visa_type_id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'nationality' => $request->nationality,
                'country_of_residence' => $request->country_of_residence,
                'job_title' => $request->job_title,
                'employment_type' => $request->employment_type,
                'monthly_income' => $request->monthly_income,
                'adults_count' => $request->adults_count,
                'children_count' => $request->children_count,
                'application_start_date' => $request->application_start_date,
                'notes' => $request->notes,
                'agreed_to_terms' => true,
                'status' => 'pending_review',
            ]);

            $this->auditLog->log('application_created', $user, ['reference' => $application->reference_number]);

            Auth::login($user);

            return $application;
        });

        $this->workflowService->seedTasksForApplication($application);
        $application->loadMissing('visaType.paymentStageConfigs');
        $this->paymentService->seedPaymentsForApplication($application);

        return $application;
    }
}
