<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('tasks.submitted_for_review_subject', ['reference' => $task->application->reference_number]) }}</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; border: 1px solid #e5e7eb;">

        <h2 style="color: #1f2937; margin-top: 0;">{{ __('tasks.submitted_for_review_heading') }}</h2>

        <p style="color: #6b7280;">{{ __('tasks.submitted_for_review_intro', ['client' => $task->application->user->name]) }}</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb; width: 40%;">
                    {{ __('reviewer.reference') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937; font-family: monospace;">
                    {{ $task->application->reference_number }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb;">
                    {{ __('tasks.task_name_label') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937;">
                    {{ $task->name }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb;">
                    {{ __('tasks.task_type_label') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937;">
                    {{ ucfirst($task->type) }}
                </td>
            </tr>
        </table>

        <p style="color: #6b7280;">{{ __('tasks.submitted_for_review_cta') }}</p>

        <p style="margin-top: 32px; color: #9ca3af; font-size: 12px;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
