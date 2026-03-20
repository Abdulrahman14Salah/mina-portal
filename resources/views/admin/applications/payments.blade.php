<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('payments.admin_payments_title') }}
            <span class="text-gray-500 font-normal"># {{ $application->reference_number }}</span>
        </h2>
        <p class="text-sm text-gray-600 mt-1">{{ $application->full_name }}</p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.stage_name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.name') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.amount') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.currency') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.paid_on') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('payments.stripe_reference') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700">
                            @foreach($payments as $payment)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $payment->stage }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $payment->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($payment->amount / 100, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ strtoupper($payment->currency) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5
                                        {{ $payment->status === 'paid' ? 'bg-green-100 text-green-800' : ($payment->status === 'due' ? 'bg-yellow-100 text-yellow-800' : ($payment->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                        {{ __('payments.status_' . $payment->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($payment->status === 'paid')
                                        {{ $payment->paid_at->format('d M Y') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-xs">
                                    @if($payment->status === 'paid')
                                        {{ $payment->stripe_payment_intent_id }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($payment->status === 'pending')
                                        <form method="POST" action="{{ route('admin.applications.payments.mark-due', [$application, $payment]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">
                                                {{ __('payments.mark_as_due') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
