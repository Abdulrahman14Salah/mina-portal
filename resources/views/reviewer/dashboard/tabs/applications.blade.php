<div class="rounded-lg bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold text-gray-900">{{ __('reviewer.active_applications') }}</h3>
        <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ $applications->count() }}</span>
    </div>

    @if ($applications->isEmpty())
        <p class="mt-4 text-gray-500">{{ __('reviewer.no_active_applications') }}</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <th class="px-4 py-3">{{ __('reviewer.reference') }}</th>
                        <th class="px-4 py-3">{{ __('reviewer.client_name') }}</th>
                        <th class="px-4 py-3">{{ __('reviewer.visa_type') }}</th>
                        <th class="px-4 py-3">{{ __('reviewer.current_step') }}</th>
                        <th class="px-4 py-3">{{ __('reviewer.submitted') }}</th>
                        <th class="px-4 py-3">{{ __('reviewer.view') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                    @foreach ($applications as $application)
                        @php($currentStep = $application->tasks->first())
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $application->reference_number }}</td>
                            <td class="px-4 py-3">{{ $application->user->name }}</td>
                            <td class="px-4 py-3">{{ $application->visaType->name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1">
                                    <span>{{ $currentStep?->name ?? '—' }}</span>
                                    @if ($currentStep)
                                        <span class="inline-flex w-fit rounded-full bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">{{ __('tasks.status_' . $currentStep->status) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $application->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3"><a href="{{ route('reviewer.applications.show', $application) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('reviewer.open_application') }}">{{ __('reviewer.view') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
