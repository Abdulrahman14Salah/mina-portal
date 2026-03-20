@props(['items' => []])

@if(count($items) > 0)
<nav aria-label="Breadcrumb" class="px-6 py-3 text-sm text-gray-500">
    <ol class="flex items-center space-x-2">
        @foreach($items as $item)
            <li class="flex items-center">
                @if(!$loop->first)
                    <span class="mx-2 text-gray-300">/</span>
                @endif
                @if($item['route'])
                    <a href="{{ route($item['route']) }}" class="hover:text-gray-700 hover:underline">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-gray-800 font-medium">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif