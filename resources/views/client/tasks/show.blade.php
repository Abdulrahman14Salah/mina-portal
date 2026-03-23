<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $task->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Task header --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $task->name }}</h1>
                        @if ($task->description)
                            <p class="mt-2 text-sm text-gray-600">{{ $task->description }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                        {{ $task->status === 'approved'    ? 'bg-green-100 text-green-700'   : '' }}
                        {{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                        {{ $task->status === 'rejected'    ? 'bg-red-100 text-red-700'       : '' }}
                        {{ $task->status === 'pending'     ? 'bg-gray-100 text-gray-600'     : '' }}">
                        {{ __('tasks.status_' . $task->status) }}
                    </span>
                </div>

                @if ($task->reviewer_note)
                    <div class="mt-4 rounded-md bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-600">
                        <p class="font-semibold mb-1">{{ __('reviewer.reviewer_note') }}</p>
                        <p>{{ $task->reviewer_note }}</p>
                    </div>
                @endif

                @if ($task->rejection_reason)
                    <div class="mt-4 rounded-md bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold mb-1">{{ __('tasks.rejection_reason') }}</p>
                        <p>{{ $task->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Type-specific UI --}}
            @if ($task->type === 'question')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._question-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._answers-readonly')
                @endif
            @elseif ($task->type === 'payment')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._payment-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._receipt-readonly')
                @endif
            @elseif ($task->type === 'info')
                @include('client.tasks.partials._info-content')
            @endif

            {{-- Back link --}}
            <div>
                <a href="{{ route('client.dashboard') }}" class="text-sm text-blue-600 hover:underline">
                    &larr; {{ __('client.back_to_dashboard') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
