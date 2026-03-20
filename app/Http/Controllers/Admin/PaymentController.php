<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkPaymentDueRequest;
use App\Models\Payment;
use App\Models\VisaApplication;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request, VisaApplication $application)
    {
        $this->authorize('manage', Payment::class);

        $payments = $application->payments()->orderBy('stage')->get();

        return view('admin.applications.payments', compact('application', 'payments'));
    }

    public function markDue(MarkPaymentDueRequest $request, VisaApplication $application, Payment $payment)
    {
        $this->authorize('manage', $payment);

        $this->paymentService->markStageAsDue($payment);

        return redirect()->route('admin.applications.payments.index', $application)->with('success', __('payments.stage_marked_due'));
    }
}
