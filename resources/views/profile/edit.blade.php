<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const frequencySelect = document.getElementById('report_frequency');
            const dailyOptions = document.getElementById('daily-options');
            const weeklyOptions = document.getElementById('weekly-options');
            const monthlyOptions = document.getElementById('monthly-options');

            function toggleOptions() {
                const value = frequencySelect.value;

                // Hide all options first
                dailyOptions.style.display = 'none';
                weeklyOptions.style.display = 'none';
                monthlyOptions.style.display = 'none';

                // Show relevant options based on selection
                if (value === 'daily') {
                    dailyOptions.style.display = 'block';
                } else if (value === 'weekly') {
                    weeklyOptions.style.display = 'block';
                } else if (value === 'monthly') {
                    monthlyOptions.style.display = 'block';
                }
            }

            // Initial check
            toggleOptions();

            // Listen for changes
            frequencySelect.addEventListener('change', toggleOptions);
        });
    </script>
</x-app-layout>
