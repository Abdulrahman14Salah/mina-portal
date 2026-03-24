@if ($application->status === 'workflow_complete')
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
        <p class="text-sm font-semibold text-green-800">{{ __('tasks.workflow_complete_title') }}</p>
        <p class="mt-1 text-sm text-green-700">{{ __('tasks.workflow_complete_message') }}</p>
    </div>
@endif

@if ($application->tasks->isEmpty())
    <div class="rounded-lg bg-white p-10 text-center shadow-sm">
        <p class="text-gray-500">{{ __('tasks.no_tasks') }}</p>
    </div>
@else
    <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm font-medium text-gray-900">{{ __('tasks.progress_summary') }}</p>
        <p class="mt-1 text-sm text-gray-500">
            {{ __('tasks.task_summary', ['completed' => $application->tasks->where('status', 'approved')->count(), 'total' => $application->tasks->count()]) }}
        </p>
    </div>

    <div class="space-y-3">
        @foreach ($application->tasks->sortBy('position') as $task)
            @php
                $isActive   = $task->status === 'in_progress';
                $isApproved = $task->status === 'approved';
                $isPending  = $task->status === 'pending';
                $isRejected = $task->status === 'rejected';
            @endphp
            <div class="rounded-lg bg-white p-6 shadow-sm {{ $isActive ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }} {{ $isApproved ? 'border-l-4 border-green-500' : '' }} {{ $isRejected ? 'border-l-4 border-red-500' : '' }} {{ $isPending ? 'opacity-50' : '' }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        @if ($isActive || $isApproved)
                            <a href="{{ route('client.tasks.show', [$application, $task]) }}" class="font-semibold text-gray-900 hover:text-indigo-600">{{ $task->name }}</a>
                        @else
                            <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                        @endif
                        @if ($task->description)
                            <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                        @endif
                        @if ($isApproved && $task->completed_at)
                            <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $isApproved ? 'bg-green-100 text-green-700' : '' }} {{ $isActive ? 'bg-indigo-100 text-indigo-700' : '' }} {{ $isPending ? 'bg-gray-100 text-gray-600' : '' }} {{ $isRejected ? 'bg-red-100 text-red-700' : '' }}">{{ __('tasks.status_' . $task->status) }}</span>
                </div>
            </div>
        @endforeach
    </div>
@endif
