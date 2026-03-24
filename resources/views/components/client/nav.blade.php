@php
    $currentTab = request()->route('tab') ?? 'overview';
    $tabs = [
        'overview'  => __('client.tab_overview'),
        'documents' => __('client.tab_documents'),
        'tasks'     => __('client.tab_tasks'),
        'payments'  => __('client.tab_payments'),
        'profile'   => __('client.tab_profile'),
        'support'   => __('client.tab_support'),
    ];
@endphp

<nav class="flex space-x-1 overflow-x-auto" aria-label="{{ __('client.dashboard_title') }}">
    @foreach($tabs as $tabKey => $label)
        <a href="{{ route('client.dashboard', $tabKey === 'overview' ? [] : ['tab' => $tabKey]) }}"
           class="whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md transition-colors
                  {{ $currentTab === $tabKey
                      ? 'bg-gray-900 text-white'
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
