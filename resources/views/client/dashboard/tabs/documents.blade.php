@php
    $docSteps = $application->tasks->sortBy('position')->filter(fn ($t) => $t->template?->is_document_required);
@endphp

@if (session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

@if ($errors->has('file') || $errors->has('application_task_id'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('documents.upload_error') }}</div>
@endif

@if ($docSteps->isEmpty())
    <div class="rounded-lg bg-white p-10 text-center shadow-sm">
        <p class="text-gray-500">{{ __('documents.no_document_steps') }}</p>
    </div>
@else
    <div class="space-y-6">
        @foreach ($docSteps as $task)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                        <p class="mt-1 text-sm text-gray-500">{{ trans_choice('documents.document_count', $task->documents->count(), ['count' => $task->documents->count()]) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $task->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}{{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}{{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}{{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">{{ __('tasks.status_' . $task->status) }}</span>
                </div>

                @if ($task->documents->isNotEmpty())
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($task->documents as $doc)
                            <li class="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                <span class="break-all text-gray-700">{{ $doc->original_filename }}</span>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-xs text-gray-400">{{ $doc->uploader->id === auth()->id() ? __('documents.uploaded_on', ['date' => $doc->created_at->format('d M Y')]) : __('documents.uploaded_by_staff') }}</span>
                                    <a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-gray-400">{{ __('documents.no_documents') }}</p>
                @endif

                @if ($task->status === 'in_progress')
                    <form method="POST" action="{{ route('client.documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="application_task_id" value="{{ $task->id }}">
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50/70 p-4">
                            <label class="block text-sm font-medium text-gray-700">{{ __('documents.file_label') }}</label>
                            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700">
                            <p class="mt-1 text-xs text-gray-400">{{ __('documents.choose_file') }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ __('documents.upload_help') }}</p>
                        </div>
                        @error('file')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('application_task_id')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">{{ __('documents.upload') }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
