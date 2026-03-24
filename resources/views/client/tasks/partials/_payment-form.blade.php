@php $receipt = $task->documents->where('source_type', 'client')->whereNull('archived_at')->first(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.payment_receipt') }}</h2>

    @if ($receipt)
        <div class="mb-4 flex items-center gap-2 text-sm text-gray-600">
            <span class="font-medium">{{ __('tasks.current_receipt') }}:</span>
            <a href="{{ route('documents.download', $receipt) }}" class="text-indigo-600 hover:underline">
                {{ $receipt->original_filename }}
            </a>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('client.tasks.receipt.upload', [$application, $task]) }}" enctype="multipart/form-data">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="receipt" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $receipt ? __('tasks.replace_receipt') : __('tasks.upload_receipt') }}
                </label>
                <input type="file" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-400">{{ __('documents.upload_help') }}</p>
            </div>
            <button type="submit"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {{ $receipt ? __('tasks.replace_receipt') : __('tasks.upload_receipt') }}
            </button>
        </div>
    </form>
</div>
