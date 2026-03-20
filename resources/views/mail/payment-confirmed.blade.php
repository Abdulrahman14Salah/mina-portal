<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('payments.payment_confirmed_subject') }}</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; {{ app()->getLocale() === 'ar' ? 'direction: rtl; text-align: right;' : '' }}">
        <h1>{{ __('payments.payment_confirmed_subject') }}</h1>

        <p>{{ __('payments.payment_confirmed_greeting', ['name' => $payment->application->user->name]) }}</p>

        <p>{{ __('payments.payment_confirmed_intro') }}</p>

        <table style="border-collapse: collapse; width: 100%; margin: 20px 0;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>{{ __('payments.payment_confirmed_stage') }}:</strong></td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;">{{ $payment->name }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>{{ __('payments.payment_confirmed_amount') }}:</strong></td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;">{{ number_format($payment->amount / 100, 2) }} {{ strtoupper($payment->currency) }}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>{{ __('payments.payment_confirmed_date') }}:</strong></td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;">{{ $payment->paid_at->format('d M Y') }}</td>
            </tr>
        </table>

        <p>{{ __('payments.payment_confirmed_outro') }}</p>
    </div>
</body>
</html>
