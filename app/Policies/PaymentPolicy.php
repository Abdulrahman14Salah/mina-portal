<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function pay(User $user, Payment $payment): bool
    {
        return $user->can('payments.pay')
            && $payment->application->user_id === $user->id
            && in_array($payment->status, ['due', 'failed'], true);
    }

    public function manage(User $user, ?Payment $payment = null): bool
    {
        return $user->can('payments.manage');
    }
}
