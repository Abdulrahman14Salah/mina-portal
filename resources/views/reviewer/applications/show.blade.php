<x-reviewer-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">

            {{-- Application Header --}}
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow-sm text-sm text-gray-700 md:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.reference') }}</p>
                    <p class="mt-1 font-medium text-gray-900 font-mono">{{ $application->reference_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.client_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->user->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.visa_type_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->visaType->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.status_label') }}</p>
                    <p class="mt-1 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ __('client.status_' . $application->status) }}</p>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            {{-- Workflow Tasks --}}
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('reviewer.workflow_progress') }}</h3>

                @foreach ($application->tasks->sortBy('position') as $task)
                    <div class="rounded-lg bg-white p-6 shadow-sm
                        {{ $task->status === 'approved'        ? 'border-l-4 border-green-500' : '' }}
                        {{ $task->status === 'in_progress'     ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }}
                        {{ $task->status === 'pending_review'  ? 'border-l-4 border-amber-500 ring-1 ring-amber-100' : '' }}
                        {{ $task->status === 'rejected'        ? 'border-l-4 border-red-500' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                                <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                                @if ($task->description)
                                    <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                                @endif
                                @if ($task->status === 'approved' && $task->completed_at)
                                    <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                                @endif
                                @if ($task->reviewer_note)
                                    <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.reviewer_note') }}</p>
                                        <p>{{ $task->reviewer_note }}</p>
                                    </div>
                                @endif
                                @if ($task->rejection_reason)
                                    <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-red-400">{{ __('tasks.rejection_reason') }}</p>
                                        <p>{{ $task->rejection_reason }}</p>
                                    </div>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                                {{ $task->status === 'approved'       ? 'bg-green-100 text-green-700'  : '' }}
                                {{ $task->status === 'in_progress'    ? 'bg-indigo-100 text-indigo-700': '' }}
                                {{ $task->status === 'pending_review' ? 'bg-amber-100 text-amber-700'  : '' }}
                                {{ $task->status === 'pending'        ? 'bg-gray-100 text-gray-600'    : '' }}
                                {{ $task->status === 'rejected'       ? 'bg-red-100 text-red-700'      : '' }}">
                                {{ __('tasks.status_' . $task->status) }}
                            </span>
                        </div>

                        @if ($task->status === 'rejected')
                            <div class="mt-4">
                                <form method="POST" action="{{ route('reviewer.applications.tasks.reopen', [$application, $task]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                                        {{ __('tasks.reopen') }}
                                    </button>
                                </form>
                            </div>
                        @elseif ($activeTask && $task->id === $activeTask->id)
                            <div class="mt-4 rounded-lg bg-amber-50/60 p-4">
                                <p class="mb-4 text-sm font-medium text-amber-900">{{ __('tasks.awaiting_your_review') }}</p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    {{-- Approve Form --}}
                                    <form method="POST" action="{{ route('reviewer.tasks.approve', $task) }}" class="space-y-3">
                                        @csrf
                                        <label class="block text-sm font-medium text-gray-700" for="approve-note-{{ $task->id }}">{{ __('reviewer.note_label') }}</label>
                                        <textarea id="approve-note-{{ $task->id }}" name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        <button type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">{{ __('tasks.approve') }}</button>
                                    </form>

                                    {{-- Reject Form --}}
                                    <form method="POST" action="{{ route('reviewer.tasks.reject', $task) }}" class="space-y-3">
                                        @csrf
                                        <label class="block text-sm font-medium text-gray-700" for="reject-reason-{{ $task->id }}">
                                            {{ __('tasks.rejection_reason') }} <span class="text-red-500">*</span>
                                        </label>
                                        <textarea id="reject-reason-{{ $task->id }}" name="rejection_reason" required minlength="5"
                                            placeholder="{{ __('tasks.rejection_reason_placeholder') }}"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                        @error('rejection_reason')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{{ __('tasks.confirm_reject') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Documents Section --}}
            @php($allDocuments = $application->tasks->flatMap(fn ($t) => $t->documents)->sortByDesc('created_at'))
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('documents.documents_section') }}</h3>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    @if ($allDocuments->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($allDocuments as $doc)
                                <li class="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="break-all font-medium text-gray-900">{{ $doc->original_filename }}</p>
                                        <p class="text-xs text-gray-400 flex items-center gap-2">
                                            <span>{{ $doc->task?->name ?? __('documents.application_documents') }} · {{ $doc->created_at->format('d M Y') }} · {{ $doc->uploader->name }}</span>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $doc->source_type === 'reviewer' ? 'bg-indigo-50 text-indigo-700' : '' }}
                                                {{ $doc->source_type === 'admin'    ? 'bg-amber-50 text-amber-700'   : '' }}
                                                {{ $doc->source_type === 'client'   ? 'bg-gray-100 text-gray-600'    : '' }}">
                                                {{ __('reviewer.source_' . $doc->source_type) }}
                                            </span>
                                        </p>
                                    </div>
                                    <a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">{{ __('documents.no_documents') }}</p>
                    @endif
                </div>
            </div>

            {{-- Reviewer Document Upload --}}
            @can('reviewerUpload', [App\Models\Document::class, $application])
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('reviewer.upload_section_title') }}</h3>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('reviewer.applications.documents.store', $application) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="upload-task">{{ __('reviewer.upload_select_task') }}</label>
                            <select id="upload-task" name="application_task_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('reviewer.upload_select_task') }}</option>
                                @foreach ($application->tasks->sortBy('position') as $task)
                                    <option value="{{ $task->id }}">{{ $task->position }}. {{ $task->name }}</option>
                                @endforeach
                            </select>
                            @error('application_task_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <input id="upload-file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.docx"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700">
                            @error('file')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('reviewer.upload_submit') }}</button>
                    </form>
                </div>
            </div>
            @endcan

        </div>
    </div>
</x-reviewer-layout>
