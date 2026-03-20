<div class="grid gap-6 lg:grid-cols-2">
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">{{ __('client.dashboard_title') }}</h3>
        <dl class="mt-4 space-y-3 text-sm text-gray-600">
            <div class="flex justify-between gap-4"><dt>{{ __('client.application_status') }}</dt><dd>{{ __('client.status_' . $application->status) }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.applied_for') }}</dt><dd>{{ $application->visaType->name }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.application_start_date') }}</dt><dd>{{ $application->application_start_date->format('d M Y') }}</dd></div>
        </dl>
    </div>
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <dl class="space-y-3 text-sm text-gray-600">
            <div class="flex justify-between gap-4"><dt>{{ __('client.full_name') }}</dt><dd>{{ $application->full_name }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.email') }}</dt><dd>{{ $application->email }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.phone') }}</dt><dd>{{ $application->phone }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.nationality') }}</dt><dd>{{ $application->nationality }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.country_of_residence') }}</dt><dd>{{ $application->country_of_residence }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.adults_count') }}</dt><dd>{{ $application->adults_count }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('client.children_count') }}</dt><dd>{{ $application->children_count }}</dd></div>
        </dl>
    </div>
</div>
