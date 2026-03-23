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

            {{-- Task Info --}}
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
                        {{ $task->status === 'approved' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                        {{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}
                        {{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}">
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

            {{-- Upload Form (upload or both, when task is open) --}}
            @if (in_array($task->type ?? 'upload', ['upload', 'both']) && in_array($task->status, ['in_progress', 'rejected']))
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('documents.upload') }}</h2>

                    @if ($errors->any())
                        <div class="mb-4 rounded-md bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('client.documents.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="application_task_id" value="{{ $task->id }}">
                        <div class="space-y-4">
                            <div>
                                <label for="file" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('documents.choose_file') }}
                                </label>
                                <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png,.docx"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                <p class="mt-1 text-xs text-gray-400">{{ __('documents.upload_help') }}</p>
                            </div>
                            <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                {{ __('documents.upload') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Text Input placeholder (text or both) --}}
            @if (in_array($task->type ?? 'upload', ['text', 'both']) && in_array($task->status, ['in_progress', 'rejected']))
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.text_input_label') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('tasks.text_input_coming_soon') }}</p>
                </div>
            @endif

            {{-- Uploaded Documents --}}
            @if (in_array($task->type ?? 'upload', ['upload', 'both']))
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('documents.documents_section') }}</h2>
                    @forelse ($task->documents as $doc)
                        <div class="flex items-center justify-between py-2 border-b text-sm text-gray-700">
                            <span>{{ $doc->original_filename }}</span>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('documents.download', $doc) }}"
                                    class="text-blue-600 hover:underline text-xs">{{ __('documents.download') }}</a>
                                @if ($task->status === 'in_progress' && $doc->uploaded_by === Auth::id())
                                    <form method="POST" action="{{ route('client.documents.destroy', $doc) }}"
                                        onsubmit="return confirm('{{ __('documents.delete_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-xs">
                                            {{ __('admin.delete') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('documents.no_documents') }}</p>
                    @endforelse
                </div>
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
