<x-reviewer-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div class="overflow-x-auto rounded-lg bg-white p-2 shadow-sm">
                    <nav class="flex min-w-max gap-2">
                        <a href="{{ route('reviewer.dashboard', ['tab' => 'applications']) }}"
                            class="rounded-md px-4 py-2 text-sm font-medium {{ $tab === 'applications' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __('reviewer.tab_applications') }}
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    @if ($pendingReviewCount > 0)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                            {{ __('reviewer.pending_review_count', ['count' => $pendingReviewCount]) }}
                        </span>
                    @endif
                    <p class="text-sm text-gray-500">{{ __('reviewer.queue_count') }}: {{ $applications->count() }}</p>
                </div>
            </div>

            @include('reviewer.dashboard.tabs.' . $tab, ['applications' => $applications])
        </div>
    </div>
</x-reviewer-layout>
