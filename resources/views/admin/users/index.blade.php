<x-admin-layout :breadcrumbs="$breadcrumbs">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.nav_users') }}</h1>
                <a href="{{ route('admin.users.create') }}" class="inline-flex inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">
                    {{ __('auth.create_user') }}
                </a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                <x-admin.table
                    :columns="[
                        'name'       => __('auth.name'),
                        'email'      => __('auth.email'),
                        'created_at' => __('admin.col_submitted'),
                    ]"
                    :rows="$users"
                    :search-query="$search"
                    :sort-by="$sortBy"
                    :sort-dir="$sortDir"
                >
                    @foreach($users as $user)
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="flex items-center gap-4">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:underline">{{ __('auth.edit') }}</a>
                                @if ($user->is_active && Auth::user()->can('deactivate', $user))
                                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}"
                                          onsubmit="return confirm('{{ __('admin.confirm_action') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('admin.action_deactivate') }}</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </x-admin.table>
            </div>
        </div>
    </div>
</x-admin-layout>
