<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create New Site') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('sites.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="name" :value="__('Site Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="backuppc_url" :value="__('BackupPC URL')" />
                                <x-text-input id="backuppc_url" class="block mt-1 w-full" type="url" name="backuppc_url" :value="old('backuppc_url')" required />
                                <x-input-error :messages="$errors->get('backuppc_url')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="connection_method" :value="__('Connection Method')" />
                                <select id="connection_method" name="connection_method" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                    <option value="ssh" {{ old('connection_method') == 'ssh' ? 'selected' : '' }}>SSH Tunnel</option>
                                    <option value="agent" {{ old('connection_method') == 'agent' ? 'selected' : '' }}>Polling Agent</option>
                                </select>
                                <x-input-error :messages="$errors->get('connection_method')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="polling_interval" :value="__('Polling Interval (minutes)')" />
                                <x-text-input id="polling_interval" class="block mt-1 w-full" type="number" name="polling_interval" :value="old('polling_interval', 30)" min="5" max="1440" required />
                                <x-input-error :messages="$errors->get('polling_interval')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="description" :value="__('Description')" />
                                <textarea id="description" name="description" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>

                        <div id="ssh-settings" class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="text-lg font-medium mb-4">SSH Connection Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="ssh_host" :value="__('SSH Host')" />
                                    <x-text-input id="ssh_host" class="block mt-1 w-full" type="text" name="ssh_host" :value="old('ssh_host')" />
                                    <x-input-error :messages="$errors->get('ssh_host')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="ssh_port" :value="__('SSH Port')" />
                                    <x-text-input id="ssh_port" class="block mt-1 w-full" type="number" name="ssh_port" :value="old('ssh_port', 22)" min="1" max="65535" />
                                    <x-input-error :messages="$errors->get('ssh_port')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="ssh_username" :value="__('SSH Username')" />
                                    <x-text-input id="ssh_username" class="block mt-1 w-full" type="text" name="ssh_username" :value="old('ssh_username')" />
                                    <x-input-error :messages="$errors->get('ssh_username')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="ssh_password" :value="__('SSH Password')" />
                                    <x-text-input id="ssh_password" class="block mt-1 w-full" type="password" name="ssh_password" :value="old('ssh_password')" />
                                    <x-input-error :messages="$errors->get('ssh_password')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div id="agent-settings" class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg" style="display: none;">
                            <h3 class="text-lg font-medium mb-4">Polling Agent Settings</h3>
                            <div>
                                <x-input-label for="api_key" :value="__('API Key')" />
                                <x-text-input id="api_key" class="block mt-1 w-full" type="text" name="api_key" :value="old('api_key')" />
                                <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="text-lg font-medium mb-4">BackupPC Authentication</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="backuppc_username" :value="__('BackupPC Username')" />
                                    <x-text-input id="backuppc_username" class="block mt-1 w-full" type="text" name="backuppc_username" :value="old('backuppc_username')" />
                                    <x-input-error :messages="$errors->get('backuppc_username')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="backuppc_password" :value="__('BackupPC Password')" />
                                    <x-text-input id="backuppc_password" class="block mt-1 w-full" type="password" name="backuppc_password" :value="old('backuppc_password')" />
                                    <x-input-error :messages="$errors->get('backuppc_password')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <x-input-label for="is_active" :value="__('Active Status')" />
                            <input id="is_active" type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button class="ml-3">
                                {{ __('Create Site') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const connectionMethod = document.getElementById('connection_method');
            const sshSettings = document.getElementById('ssh-settings');
            const agentSettings = document.getElementById('agent-settings');

            function toggleSettings() {
                if (connectionMethod.value === 'ssh') {
                    sshSettings.style.display = 'block';
                    agentSettings.style.display = 'none';
                } else {
                    sshSettings.style.display = 'none';
                    agentSettings.style.display = 'block';
                }
            }

            connectionMethod.addEventListener('change', toggleSettings);
            toggleSettings();
        });
    </script>
</x-app-layout>