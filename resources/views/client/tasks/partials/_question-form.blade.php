@php $questions = $task->workflowTask?->questions ?? collect(); @endphp

@if ($questions->isEmpty())
    @include('client.tasks.partials._no-questions')
@else
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.your_answers') }}</h2>

        <form method="POST" action="{{ route('client.tasks.answers.submit', [$application, $task]) }}">
            @csrf
            <div class="space-y-6">
                @foreach ($questions as $question)
                    <div>
                        <label for="answer_{{ $question->id }}" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $question->prompt }}
                            @if ($question->required)
                                <span class="text-red-500 ml-0.5">*</span>
                            @endif
                        </label>
                        <textarea
                            id="answer_{{ $question->id }}"
                            name="answers[{{ $question->id }}]"
                            rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('answers.' . $question->id) border-red-500 @enderror"
                        >{{ old('answers.' . $question->id, $answers[$question->id]->answer ?? '') }}</textarea>
                        @error('answers.' . $question->id)
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    {{ __('tasks.submit_answers') }}
                </button>
            </div>
        </form>
    </div>
@endif
