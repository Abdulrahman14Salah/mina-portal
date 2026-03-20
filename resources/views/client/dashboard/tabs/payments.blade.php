<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="mb-4 text-lg font-semibold">{{ __('payments.payment_tab_title') }}</h2>

    @if($payments->isEmpty())
        <p class="text-gray-500">{{ __('client.empty_payments') }}</p>
    @else
        <div class="space-y-4">
            @foreach($payments as $payment)
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">{{ __('payments.stage_name') }} {{ $payment->stage }}: {{ $payment->name }}</p>
                            <p class="text-sm text-gray-600">
                                {{ number_format($payment->amount / 100, 2) }} {{ strtoupper($payment->currency) }}
                            </p>
                        </div>
                        <div class="text-right">
                            @if($payment->status === 'due' || $payment->status === 'failed')
                                <a href="{{ route('client.payments.checkout', $payment) }}" class="inline-block rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                                    {{ __('payments.pay_now') }}
                                </a>
                            @elseif($payment->status === 'paid')
                                <p class="text-sm text-green-600">
                                    {{ __('payments.paid_on') }} {{ $payment->paid_at->format('d M Y') }}
                                </p>
                            @else
                                <span class="inline-block rounded px-3 py-1 text-sm {{ $payment->status === 'pending' ? 'bg-gray-100 text-gray-600' : ($payment->status === 'failed' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600') }}">
                                    {{ __('payments.status_' . $payment->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
