<nav class="flex space-x-1 overflow-x-auto" aria-label="{{ __('admin.breadcrumb_home') }}">
    @foreach(config('admin-navigation') as $item)
        @php
            $pattern = $item['active_pattern'] ?? $item['route'];
            $isActive = request()->routeIs($pattern);
        @endphp
        <a href="{{ route($item['route']) }}"
           class="whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md transition-colors
                  {{ $isActive
                      ? 'bg-gray-900'
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            {{ __($item['label_key']) }}
        </a>
    @endforeach
</nav>