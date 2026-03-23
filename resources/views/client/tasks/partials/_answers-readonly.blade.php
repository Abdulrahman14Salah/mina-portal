@php $questions = $task->workflowTask?->questions ?? collect(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.answers_readonly_title') }}</h2>

    @forelse ($questions as $question)
        <div class="mb-4">
            <p class="text-sm font-medium text-gray-700">{{ $question->prompt }}</p>
            <p class="mt-1 text-sm text-gray-600 bg-gray-50 rounded-md px-3 py-2">
                {{ $answers[$question->id]->answer ?? '—' }}
            </p>
        </div>
    @empty
        <p class="text-sm text-gray-500">{{ __('tasks.no_questions_defined') }}</p>
    @endforelse
</div>
