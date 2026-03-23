<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Application {{ $application->reference_number }} - {{ __('documents.all_documents') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow-sm text-sm text-gray-700 md:grid-cols-3">
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
                    <p class="mt-1 font-medium text-gray-900">{{ __('client.status_' . $application->status) }}</p>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if ($errors->has('file') || $errors->has('application_task_id'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ __('documents.upload_error') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('documents.upload_on_behalf') }}</h3>
                <form method="POST" action="{{ route('admin.applications.documents.store', $application) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('documents.step_label') }}</label>
                        <select name="application_task_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($application->tasks->sortBy('position') as $task)
                                <option value="{{ $task->id }}">{{ $task->position }}. {{ $task->name }}</option>
                            @endforeach
                        </select>
                        @error('application_task_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50/70 p-4">
                        <label class="block text-sm font-medium text-gray-700">{{ __('documents.file_label') }}</label>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700">
                        <p class="mt-1 text-xs text-gray-400">{{ __('documents.choose_file') }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('documents.upload_help') }}</p>
                        @error('file')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">{{ __('documents.upload') }}</button>
                </form>
            </div>

            <div class="space-y-4">
                @foreach ($application->tasks->sortBy('position') as $task)
                    @if ($task->documents->isNotEmpty())
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                                <span class="text-xs text-gray-400">{{ trans_choice('documents.document_count', $task->documents->count(), ['count' => $task->documents->count()]) }}</span>
                            </div>
                            <ul class="mt-3 divide-y divide-gray-100">
                                @foreach ($task->documents as $doc)
                                    <li class="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="break-all font-medium text-gray-900">{{ $doc->original_filename }}</p>
                                            <p class="text-xs text-gray-400">{{ $doc->uploader->id !== $application->user_id ? __('documents.uploaded_by_staff') : $application->user->name }} - {{ $doc->created_at->format('d M Y') }}</p>
                                        </div>
                                        <a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
