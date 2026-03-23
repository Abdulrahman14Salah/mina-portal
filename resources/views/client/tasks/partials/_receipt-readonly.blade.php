@php $receipt = $task->documents->where('source_type', 'client')->whereNull('archived_at')->first(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('tasks.payment_receipt') }}</h2>

    @if ($receipt)
        <a href="{{ route('documents.download', $receipt) }}" class="text-sm text-indigo-600 hover:underline">
            {{ $receipt->original_filename }}
        </a>
    @else
        <p class="text-sm text-gray-500">—</p>
    @endif
</div>
