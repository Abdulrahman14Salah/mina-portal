@if ($application->tasks->isEmpty())
    <div class="rounded-lg bg-white p-10 text-center shadow-sm">
        <p class="text-gray-500">{{ __('tasks.no_tasks') }}</p>
    </div>
@else
    <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm font-medium text-gray-900">{{ __('tasks.progress_summary') }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ $application->tasks->where('status', 'completed')->count() }} / {{ $application->tasks->count() }}</p>
    </div>

    <div class="space-y-3">
        @foreach ($application->tasks->sortBy('position') as $task)
            <div class="rounded-lg bg-white p-6 shadow-sm {{ $task->status === 'in_progress' ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }} {{ $task->status === 'completed' ? 'border-l-4 border-green-500' : '' }} {{ $task->status === 'rejected' ? 'border-l-4 border-red-500' : '' }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                        @if ($task->description)
                            <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                        @endif
                        @if ($task->status === 'completed' && $task->completed_at)
                            <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $task->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}{{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}{{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}{{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">{{ __('tasks.status_' . $task->status) }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif
