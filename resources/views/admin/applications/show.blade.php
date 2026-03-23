<x-admin-layout :breadcrumbs="$breadcrumbs ?? []">
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-800">
                    {{ __('admin.nav_applications') }}: <span class="font-mono">{{ $application->reference_number }}</span>
                </h1>
                <a href="{{ route('admin.applications.index') }}" class="text-sm text-blue-600 hover:underline">
                    &larr; {{ __('admin.back_to_list') }}
                </a>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Application Info --}}
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow-sm text-sm text-gray-700 md:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.col_reference') }}</p>
                    <p class="mt-1 font-mono font-medium text-gray-900">{{ $application->reference_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.col_status') }}</p>
                    <p class="mt-1">
                        <span class="rounded-full px-2 py-1 text-xs font-medium bg-gray-100 text-gray-600">
                            {{ $application->status }}
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.client_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->user->name }}</p>
                    <p class="text-xs text-gray-400">{{ $application->user->email }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.visa_type_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->visaType->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('admin.submitted_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->created_at->format('d M Y') }}</p>
                </div>
            </div>

            {{-- Reviewer Assignment --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('admin.reviewer_assignment') }}</h2>

                @if ($application->assignedReviewer)
                    <p class="text-sm text-gray-700 mb-4">
                        {{ __('admin.currently_assigned_to') }}:
                        <span class="font-medium">{{ $application->assignedReviewer->name }}</span>
                        ({{ $application->assignedReviewer->email }})
                    </p>
                @else
                    <p class="text-sm text-gray-500 mb-4">{{ __('admin.no_reviewer_assigned') }}</p>
                @endif

                <form method="POST" action="{{ route('admin.applications.assign-reviewer', $application) }}" class="flex items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="reviewer_id" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.select_reviewer') }}
                        </label>
                        <select name="reviewer_id" id="reviewer_id"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— {{ __('admin.unassign') }} —</option>
                                @foreach ($reviewers as $reviewer)
                                    <option value="{{ $reviewer->id }}"
                                        {{ $application->assigned_reviewer_id == $reviewer->id ? 'selected' : '' }}>
                                        {{ $reviewer->name }}
                                    </option>
                                @endforeach
                            </select>
                    </div>
                    <button type="submit"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            {{ __('admin.save_assignment') }}
                    </button>
                </form>
            </div>

            {{-- Workflow Tasks --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('reviewer.workflow_progress') }}</h2>
                <div class="space-y-3">
                    @forelse ($application->tasks->sortBy('position') as $task)
                        <div class="flex items-center justify-between rounded-md border px-4 py-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-400">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                                <p class="font-medium text-gray-900">{{ $task->name }}</p>
                                @if ($task->reviewer_note)
                                    <p class="text-xs text-gray-500 mt-1">{{ $task->reviewer_note }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-medium
                                {{ $task->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                                {{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}">
                                {{ __('tasks.status_' . $task->status) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('tasks.no_tasks') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Documents quick link --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('documents.documents_section') }}</h2>
                <a href="{{ route('admin.applications.documents.index', $application) }}"
                       class="text-sm text-blue-600 hover:underline">
                        {{ __('documents.all_documents') }} &rarr;
                </a>
            </div>

        </div>
    </div>
</x-admin-layout>
