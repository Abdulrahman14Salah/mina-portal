<x-client-layout>
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm p-8 text-center">
                <svg class="mx-auto mb-4 h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-800">{{ __('client.no_application_title') }}</h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('client.no_application_body') }}</p>
            </div>
        </div>
    </div>
</x-client-layout>
