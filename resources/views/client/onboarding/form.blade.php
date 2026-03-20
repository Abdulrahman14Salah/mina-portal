<x-guest-layout>
    <div x-data="{ step: 1 }" class="space-y-6">
        <div class="text-center text-sm text-gray-500">
            <span x-show="step === 1">{{ __('client.step_indicator', ['current' => 1, 'total' => 3]) }}</span>
            <span x-show="step === 2">{{ __('client.step_indicator', ['current' => 2, 'total' => 3]) }}</span>
            <span x-show="step === 3">{{ __('client.step_indicator', ['current' => 3, 'total' => 3]) }}</span>
        </div>

        <form method="POST" action="{{ route('onboarding.store') }}" class="space-y-6">
            @csrf

            <div x-show="step === 1" x-cloak class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-900">{{ __('client.wizard_step1_title') }}</h2>
                <div>
                    <x-input-label for="full_name" :value="__('client.full_name')" />
                    <x-text-input id="full_name" class="mt-1 block w-full" type="text" name="full_name" :value="old('full_name')" required />
                    <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('client.email')" />
                    <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password" :value="__('client.password')" />
                    <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" :value="__('client.confirm_password')" />
                    <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required />
                </div>
                <div>
                    <x-input-label for="phone" :value="__('client.phone')" />
                    <x-text-input id="phone" class="mt-1 block w-full" type="text" name="phone" :value="old('phone')" required />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="nationality" :value="__('client.nationality')" />
                    <x-text-input id="nationality" class="mt-1 block w-full" type="text" name="nationality" :value="old('nationality')" required />
                    <x-input-error :messages="$errors->get('nationality')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="country_of_residence" :value="__('client.country_of_residence')" />
                    <x-text-input id="country_of_residence" class="mt-1 block w-full" type="text" name="country_of_residence" :value="old('country_of_residence')" required />
                    <x-input-error :messages="$errors->get('country_of_residence')" class="mt-2" />
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="step = 2" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('client.next') }}</button>
                </div>
            </div>

            <div x-show="step === 2" x-cloak class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-900">{{ __('client.wizard_step2_title') }}</h2>
                <div>
                    <x-input-label for="visa_type_id" :value="__('client.visa_type')" />
                    <select id="visa_type_id" name="visa_type_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">{{ __('client.select_visa_type') }}</option>
                        @foreach ($visaTypes as $vt)
                            <option value="{{ $vt->id }}" @selected(old('visa_type_id') == $vt->id)>{{ $vt->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('visa_type_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="adults_count" :value="__('client.adults_count')" />
                    <x-text-input id="adults_count" class="mt-1 block w-full" type="number" name="adults_count" min="1" :value="old('adults_count', 1)" required />
                    <x-input-error :messages="$errors->get('adults_count')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="children_count" :value="__('client.children_count')" />
                    <x-text-input id="children_count" class="mt-1 block w-full" type="number" name="children_count" min="0" :value="old('children_count', 0)" required />
                    <x-input-error :messages="$errors->get('children_count')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="application_start_date" :value="__('client.application_start_date')" />
                    <x-text-input id="application_start_date" class="mt-1 block w-full" type="date" name="application_start_date" :value="old('application_start_date')" required />
                    <x-input-error :messages="$errors->get('application_start_date')" class="mt-2" />
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="step = 1" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">{{ __('client.back') }}</button>
                    <button type="button" @click="step = 3" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white">{{ __('client.next') }}</button>
                </div>
            </div>

            <div x-show="step === 3" x-cloak class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-900">{{ __('client.wizard_step3_title') }}</h2>
                <div>
                    <x-input-label for="job_title" :value="__('client.job_title')" />
                    <x-text-input id="job_title" class="mt-1 block w-full" type="text" name="job_title" :value="old('job_title')" required />
                    <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employment_type" :value="__('client.employment_type')" />
                    <select id="employment_type" name="employment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="employed" @selected(old('employment_type') === 'employed')>{{ __('client.employment_employed') }}</option>
                        <option value="self_employed" @selected(old('employment_type') === 'self_employed')>{{ __('client.employment_self_employed') }}</option>
                        <option value="unemployed" @selected(old('employment_type') === 'unemployed')>{{ __('client.employment_unemployed') }}</option>
                        <option value="student" @selected(old('employment_type') === 'student')>{{ __('client.employment_student') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('employment_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="monthly_income" :value="__('client.monthly_income')" />
                    <x-text-input id="monthly_income" class="mt-1 block w-full" type="number" step="0.01" min="0" name="monthly_income" :value="old('monthly_income')" required />
                    <x-input-error :messages="$errors->get('monthly_income')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="notes" :value="__('client.notes')" />
                    <textarea id="notes" name="notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
                <label for="agreed_to_terms" class="flex items-start gap-3 text-sm text-gray-600">
                    <input id="agreed_to_terms" type="checkbox" name="agreed_to_terms" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" required @checked(old('agreed_to_terms'))>
                    <span>{{ __('client.agreed_to_terms') }}</span>
                </label>
                <x-input-error :messages="$errors->get('agreed_to_terms')" class="mt-2" />
                <div class="flex justify-between">
                    <button type="button" @click="step = 2" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">{{ __('client.back') }}</button>
                    <x-primary-button>{{ __('client.submit_application') }}</x-primary-button>
                </div>
            </div>
        </form>
    </div>
</x-guest-layout>
