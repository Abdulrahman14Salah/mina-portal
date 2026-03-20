@props(['application' => null])

<x-app-layout>
    <x-slot name="header">
        @if($application)
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $application->full_name }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('tasks.progress_summary') }}</p>
                </div>
                <span class="text-sm text-gray-500">
                    {{ __('client.application_reference') }}: <span class="font-mono font-medium">{{ $application->reference_number }}</span>
                </span>
            </div>
            <x-client.nav />
        @else
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('client.dashboard_title') }}</h2>
        @endif
    </x-slot>

    {{ $slot }}
</x-app-layout>
