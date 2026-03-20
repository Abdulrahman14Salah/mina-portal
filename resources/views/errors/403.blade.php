<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            403 — {{ __('admin.access_denied') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                <p class="text-gray-700">{{ __('admin.access_denied') }}</p>
                <a href="{{ route('dashboard') }}" class="mt-4 inline-block text-blue-600 hover:underline">
                    {{ __('admin.return_to_dashboard') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
