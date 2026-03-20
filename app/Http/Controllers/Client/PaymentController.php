<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function checkout(Request $request, Payment $payment)
    {
        $this->authorize('pay', $payment);

        $url = $this->paymentService->createCheckoutSession($payment, $request->user());

        return redirect($url);
    }

    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $this->paymentService->confirmFromSuccessUrl($sessionId);
        }

        return redirect()->route('client.dashboard', ['tab' => 'payments'])
            ->with('success', __('payments.payment_confirmed'));
    }

    public function cancel(Request $request)
    {
        return redirect()->route('client.dashboard', ['tab' => 'payments'])->with('info', __('payments.payment_cancelled'));
    }
}
