<x-admin-layout :breadcrumbs="$breadcrumbs">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.nav_applications') }}</h1>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                <x-admin.table
                    :columns="[
                        'reference_number' => __('admin.col_reference'),
                        'created_at'       => __('admin.col_submitted'),
                        'status'           => __('admin.col_status'),
                    ]"
                    :rows="$applications"
                    :search-query="$search"
                    :sort-by="$sortBy"
                    :sort-dir="$sortDir"
                >
                    @foreach($applications as $app)
                    <tr>
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm">{{ $app->reference_number }}</span>
                        </td>
                        <td class="px-6 py-4">{{ $app->created_at->format('d M Y') }}</td>
                        <td class="px-6 py-4">
                            <span class="rounded-full px-2 py-1 text-xs font-medium
                                {{ $app->status === 'approved' ? 'bg-green-100 text-green-700' :
                                   ($app->status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' :
                                   'bg-gray-100 text-gray-600') }}">
                                {{ $app->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('admin.applications.documents.index', $app) }}" class="text-sm text-blue-600 hover:underline">
                                {{ __('admin.action_view') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </x-admin.table>
            </div>
        </div>
    </div>
</x-admin-layout>
