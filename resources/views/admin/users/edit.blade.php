<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('auth.edit_user') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="name" :value="__('auth.name')" />
                    <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name', $user->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('auth.email')" />
                    <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email', $user->email)" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <x-primary-button>{{ __('auth.save_user') }}</x-primary-button>
            </form>

            @can('assignRole', $user)
                <form method="POST" action="{{ route('admin.users.assign-role', $user) }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="role" :value="__('auth.role')" />
                        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ __('auth.role_'.$role) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <x-primary-button>{{ __('auth.assign_role') }}</x-primary-button>
                </form>
            @endcan
        </div>
    </div>
</x-app-layout>
