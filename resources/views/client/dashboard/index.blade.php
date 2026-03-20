<x-client-layout :application="$application">
    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @include('client.dashboard.tabs.' . $tab, ['application' => $application, 'payments' => $payments])

        </div>
    </div>
</x-client-layout>
