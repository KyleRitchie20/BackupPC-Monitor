<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Report Scheduling') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Configure when you want to receive backup reports.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="report_frequency" :value="__('Report Frequency')" />
            <select id="report_frequency" name="report_frequency" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md">
                <option value="">{{ __('No reports') }}</option>
                <option value="daily" {{ old('report_frequency', $user->report_frequency) === 'daily' ? 'selected' : '' }}>{{ __('Daily') }}</option>
                <option value="weekly" {{ old('report_frequency', $user->report_frequency) === 'weekly' ? 'selected' : '' }}>{{ __('Weekly') }}</option>
                <option value="monthly" {{ old('report_frequency', $user->report_frequency) === 'monthly' ? 'selected' : '' }}>{{ __('Monthly') }}</option>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('report_frequency')" />
        </div>

        <div id="daily-options" class="space-y-2" style="{{ old('report_frequency', $user->report_frequency) === 'daily' ? '' : 'display: none;' }}">
            <x-input-label :value="__('Select days for daily reports')" />
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                @php $selectedDays = old('report_days', $user->report_days ?? []); @endphp
                <label class="flex items-center">
                    <input type="checkbox" name="report_days[]" value="monday" {{ in_array('monday', $selectedDays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm">{{ __('Monday') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="report_days[]" value="tuesday" {{ in_array('tuesday', $selectedDays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm">{{ __('Tuesday') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="report_days[]" value="wednesday" {{ in_array('wednesday', $selectedDays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm">{{ __('Wednesday') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="report_days[]" value="thursday" {{ in_array('thursday', $selectedDays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm">{{ __('Thursday') }}</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="report_days[]" value="friday" {{ in_array('friday', $selectedDays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm">{{ __('Friday') }}</span>
                </label>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('report_days')" />
        </div>

        <div id="weekly-options" class="space-y-2" style="{{ old('report_frequency', $user->report_frequency) === 'weekly' ? '' : 'display: none;' }}">
            <x-input-label :value="__('Select day for weekly report')" />
            <select name="report_days[]" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md">
                <option value="">{{ __('Select a day') }}</option>
                @php $selectedDay = is_array(old('report_days', $user->report_days)) ? (old('report_days', $user->report_days)[0] ?? '') : old('report_days', $user->report_days); @endphp
                <option value="monday" {{ $selectedDay === 'monday' ? 'selected' : '' }}>{{ __('Monday') }}</option>
                <option value="tuesday" {{ $selectedDay === 'tuesday' ? 'selected' : '' }}>{{ __('Tuesday') }}</option>
                <option value="wednesday" {{ $selectedDay === 'wednesday' ? 'selected' : '' }}>{{ __('Wednesday') }}</option>
                <option value="thursday" {{ $selectedDay === 'thursday' ? 'selected' : '' }}>{{ __('Thursday') }}</option>
                <option value="friday" {{ $selectedDay === 'friday' ? 'selected' : '' }}>{{ __('Friday') }}</option>
                <option value="saturday" {{ $selectedDay === 'saturday' ? 'selected' : '' }}>{{ __('Saturday') }}</option>
                <option value="sunday" {{ $selectedDay === 'sunday' ? 'selected' : '' }}>{{ __('Sunday') }}</option>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('report_days')" />
        </div>

        <div id="monthly-options" class="text-sm text-gray-600" style="{{ old('report_frequency', $user->report_frequency) === 'monthly' ? '' : 'display: none;' }}">
            {{ __('Monthly reports will be sent on the 1st of each month.') }}
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
