@props(['breadcrumbs' => []])

<x-app-layout>
    <x-slot name="header">
        <x-admin.nav />
    </x-slot>

    <x-admin.breadcrumb :items="$breadcrumbs" />

    {{ $slot }}
</x-app-layout>