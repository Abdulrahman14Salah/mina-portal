@props([
    'columns' => [],
    'rows',
    'searchQuery' => '',
    'sortBy' => 'created_at',
    'sortDir' => 'desc',
    'searchAction' => null,
])

@php $action = $searchAction ?? request()->url(); @endphp

<div>
    {{-- Search Form --}}
    <form method="GET" action="{{ $action }}" class="mb-4 flex gap-2">
        @foreach(request()->except(['search', 'page']) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <input
            type="text"
            name="search"
            value="{{ $searchQuery }}"
            placeholder="{{ __('admin.search_placeholder') }}"
            class="rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 flex-1"
        >
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            {{ __('admin.search_button') }}
        </button>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($columns as $key => $label)
                        @php
                            $newDir = ($sortBy === $key && $sortDir === 'asc') ? 'desc' : 'asc';
                            $sortUrl = request()->fullUrlWithQuery(['sort_by' => $key, 'sort_dir' => $newDir, 'page' => 1]);
                        @endphp
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                                {{ $label }}
                                @if($sortBy === $key)
                                    <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        {{ __('admin.col_actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700">
                @if(count($rows) === 0)
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-6 py-8 text-center text-gray-400">
                            {{ __('admin.no_records') }}
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(method_exists($rows, 'links'))
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    @endif
</div>