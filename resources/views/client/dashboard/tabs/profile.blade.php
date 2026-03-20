<div class="rounded-lg bg-white p-6 shadow-sm">
    <dl class="space-y-3 text-sm text-gray-600">
        <div class="flex justify-between gap-4"><dt>{{ __('client.full_name') }}</dt><dd>{{ $application->full_name }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.email') }}</dt><dd>{{ $application->email }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.phone') }}</dt><dd>{{ $application->phone }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.nationality') }}</dt><dd>{{ $application->nationality }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.country_of_residence') }}</dt><dd>{{ $application->country_of_residence }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.job_title') }}</dt><dd>{{ $application->job_title }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.employment_type') }}</dt><dd>{{ __('client.employment_' . $application->employment_type) }}</dd></div>
        <div class="flex justify-between gap-4"><dt>{{ __('client.monthly_income') }}</dt><dd>{{ number_format((float) $application->monthly_income, 2) }}</dd></div>
    </dl>
</div>
