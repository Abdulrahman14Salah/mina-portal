<x-admin-layout :breadcrumbs="$breadcrumbs ?? []">
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.nav_task_builder') }}</h1>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Add section form --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('admin.add_section') }}</h2>
                <form method="POST" action="{{ route('admin.task-builder.sections.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label for="visa_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.visa_type_label') }}
                        </label>
                        <select name="visa_type_id" id="visa_type_id"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($visaTypes as $vt)
                                <option value="{{ $vt->id }}">{{ $vt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="section_name" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.section_name') }}
                        </label>
                        <input type="text" name="name" id="section_name"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="{{ __('admin.section_name_placeholder') }}" required>
                    </div>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('admin.add_section') }}
                    </button>
                </form>
            </div>

            {{-- Visa types + sections list --}}
            @foreach ($visaTypes as $visaType)
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $visaType->name }}</h2>

                    @forelse ($visaType->workflowSections as $section)
                        <div class="mb-6 border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-medium text-gray-800">{{ $section->position }}. {{ $section->name }}</h3>
                                <form method="POST" action="{{ route('admin.task-builder.sections.destroy', $section) }}"
                                    onsubmit="return confirm('{{ __('admin.confirm_delete_section') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('admin.delete') }}</button>
                                </form>
                            </div>

                            {{-- Tasks in this section --}}
                            @forelse ($section->tasks as $task)
                                <div class="flex items-center justify-between py-2 border-b text-sm text-gray-700">
                                    <span>
                                        {{ $task->position }}. {{ $task->name }}
                                        <span class="ml-2 text-xs text-gray-400">({{ $task->type }})</span>
                                        @if ($task->type === 'question' && $task->approval_mode)
                                            <span class="ml-1 text-xs text-indigo-500">[{{ $task->approval_mode }}]</span>
                                        @endif
                                    </span>
                                    <form method="POST" action="{{ route('admin.task-builder.tasks.destroy', $task) }}"
                                        onsubmit="return confirm('{{ __('admin.confirm_delete_task') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('admin.delete') }}</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-400 py-2">{{ __('admin.no_tasks_in_section') }}</p>
                            @endforelse

                            {{-- Add task to this section --}}
                            <form method="POST" action="{{ route('admin.task-builder.tasks.store', $section) }}"
                                class="mt-3 flex flex-wrap items-end gap-2" x-data="{ taskType: 'question' }">
                                @csrf
                                <div>
                                    <input type="text" name="name"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="{{ __('admin.task_name_placeholder') }}" required>
                                </div>
                                <div>
                                    <input type="text" name="description"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="{{ __('admin.task_description_placeholder') }}">
                                </div>
                                <div>
                                    <select name="type" x-model="taskType"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="question">{{ __('admin.task_type_question') }}</option>
                                        <option value="payment">{{ __('admin.task_type_payment') }}</option>
                                        <option value="info">{{ __('admin.task_type_info') }}</option>
                                    </select>
                                </div>
                                <div x-show="taskType === 'question'">
                                    <select name="approval_mode"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="manual">{{ __('admin.approval_mode_manual') }}</option>
                                        <option value="auto">{{ __('admin.approval_mode_auto') }}</option>
                                    </select>
                                </div>
                                <button type="submit"
                                    class="inline-flex items-center rounded-md bg-gray-700 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-600">
                                    {{ __('admin.add_task') }}
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">{{ __('admin.no_sections_yet') }}</p>
                    @endforelse
                </div>
            @endforeach

        </div>
    </div>
</x-admin-layout>
