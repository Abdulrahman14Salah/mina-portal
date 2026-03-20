<x-admin-layout :breadcrumbs="$breadcrumbs">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-gray-800">{{ $section }}</h1>
                <p class="mt-2 text-gray-500">{{ __('admin.coming_soon') }}</p>
            </div>
        </div>
    </div>
</x-admin-layout>