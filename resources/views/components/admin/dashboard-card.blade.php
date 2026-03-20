@props(['widget', 'title', 'href' => null])

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ $title }}</h3>
    @if($widget['error'])
        <p class="mt-2 text-sm text-red-500">{{ $widget['error'] }}</p>
    @else
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($widget['data']) }}</p>
        @if($href)
            <a href="{{ $href }}" class="mt-2 inline-block text-sm text-blue-600 hover:underline">
                {{ __('admin.view_all') }} →
            </a>
        @endif
    @endif
</div>