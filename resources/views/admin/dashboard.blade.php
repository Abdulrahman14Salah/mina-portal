<x-admin-layout :breadcrumbs="$breadcrumbs">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.dashboard_title') }}</h1>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-admin.dashboard-card
                    :widget="$widgets['active_count']"
                    :title="__('admin.active_applications')"
                    :href="route('admin.applications.index')" />

                <x-admin.dashboard-card
                    :widget="$widgets['pending_count']"
                    :title="__('admin.pending_review')"
                    :href="route('admin.applications.index', ['status' => 'pending_review'])" />

                <x-admin.dashboard-card
                    :widget="$widgets['client_count']"
                    :title="__('admin.total_clients')"
                    :href="route('admin.clients.index')" />
            </div>

            {{-- Recent Applications --}}
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-700">{{ __('admin.recent_applications') }}</h2>
                </div>
                @if($widgets['recent']['error'])
                    <p class="px-6 pb-4 text-sm text-red-500">{{ $widgets['recent']['error'] }}</p>
                @elseif($widgets['recent']['data'] && $widgets['recent']['data']->isNotEmpty())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_reference') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_client') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_visa_type') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_submitted') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700">
                            @foreach($widgets['recent']['data'] as $app)
                            <tr>
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm">{{ $app->reference_number }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $app->user?->name ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $app->visaType?->name ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $app->status }}</td>
                                <td class="px-6 py-4">{{ $app->created_at->format('d M Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="px-6 pb-4 text-sm text-gray-500">{{ __('admin.no_recent_applications') }}</p>
                @endif
            </div>

        </div>
    </div>
</x-admin-layout>