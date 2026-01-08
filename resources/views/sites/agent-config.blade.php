<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Agent Configuration') }} - {{ $site->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <!-- Agent Status -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-4">{{ __('Agent Status') }}</h3>
                        <div class="flex items-center gap-4">
                            @if($site->hasActiveAgent())
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <span class="w-2 h-2 mr-2 bg-green-500 rounded-full"></span>
                                    {{ __('Agent Connected') }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Last contact: ') }}{{ $site->last_agent_contact->diffForHumans() }}
                                </span>
                            @elseif($site->agent_token)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    <span class="w-2 h-2 mr-2 bg-yellow-500 rounded-full"></span>
                                    {{ __('Agent Configured (Offline)') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    <span class="w-2 h-2 mr-2 bg-gray-500 rounded-full"></span>
                                    {{ __('Agent Not Configured') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Agent Token -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-4">{{ __('Agent Authentication') }}</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ __('Agent Token') }}
                                    </label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('This token must be copied to the BackupPC server agent configuration.') }}
                                    </p>
                                </div>
                                <form action="{{ route('sites.generate-token', $site) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            onclick="return confirm('{{ __('Are you sure you want to regenerate the agent token? This will disconnect the existing agent.') }}')"
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        {{ __('Regenerate Token') }}
                                    </button>
                                </form>
                            </div>
                            <div class="relative">
                                <input type="password"
                                       id="agent-token"
                                       value="{{ $site->agent_token ?? '' }}"
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 font-mono text-sm">
                                <button type="button"
                                        onclick="toggleTokenVisibility()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <button type="button"
                                    onclick="copyToken()"
                                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                {{ __('Copy to clipboard') }}
                            </button>
                        </div>
                    </div>

                    <!-- Agent Configuration -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-4">{{ __('Agent Installation') }}</h3>
                        <div class="space-y-4">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('1. Download Agent Files') }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ __('Copy the agent directory from the dashboard to your BackupPC server:') }}
                                </p>
                                <pre class="bg-gray-800 text-gray-100 rounded-md p-3 text-sm overflow-x-auto"><code>scp -r agent/ backuppc@your-backuppc-server:/opt/backuppc-monitor-agent/</code></pre>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('2. Configure Agent') }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ __('Copy the example config and edit with your settings:') }}
                                </p>
                                <pre class="bg-gray-800 text-gray-100 rounded-md p-3 text-sm overflow-x-auto"><code>cd /opt/backuppc-monitor-agent
cp config.example.php config.php
# Edit config.php with your site_id and agent_token</code></pre>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('3. Run Agent') }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ __('Test the agent manually:') }}
                                </p>
                                <pre class="bg-gray-800 text-gray-100 rounded-md p-3 text-sm overflow-x-auto"><code>php /opt/backuppc-monitor-agent/agent.php \
    --site-id={{ $site->id }} \
    --agent-token={{ $site->agent_token }} \
    --dashboard-url={{ config('app.url') }}</code></pre>
                            </div>

                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ __('4. Install Systemd Service (Optional)') }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    {{ __('Install the agent as a systemd service for automatic startup:') }}
                                </p>
                                <pre class="bg-gray-800 text-gray-100 rounded-md p-3 text-sm overflow-x-auto"><code>sudo cp backuppc-monitor-agent.service /etc/systemd/system/
sudo chmod 644 /etc/systemd/system/backuppc-monitor-agent.service
# Edit the service file to set your SITE_ID and AGENT_TOKEN
sudo systemctl daemon-reload
sudo systemctl enable backuppc-monitor-agent
sudo systemctl start backuppc-monitor-agent</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Configuration File Template -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium mb-4">{{ __('Generated Configuration') }}</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            {{ __('Copy this configuration to your BackupPC server config.php file:') }}
                        </p>
                        <pre class="bg-gray-800 text-gray-100 rounded-md p-4 text-sm overflow-x-auto"><code><?php
echo "return [\n";
echo "    'dashboard_url' => '" . config('app.url') . "',\n";
echo "    'site_id' => " . $site->id . ",\n";
echo "    'agent_token' => '" . ($site->agent_token ?? 'YOUR_TOKEN_HERE') . "',\n";
echo "    'backuppc_url' => '" . $site->backuppc_url . "',\n";
echo "    'backuppc_username' => '" . ($site->backuppc_username ?? '') . "',\n";
echo "    'api_key' => '',  // Will be decrypted from dashboard\n";
echo "    'polling_interval' => " . $site->polling_interval . ",\n";
echo "    'heartbeat_interval' => 300,\n";
echo "    'log_file' => '/var/log/backuppc-monitor-agent.log',\n";
echo "    'pid_file' => '/var/run/backuppc-monitor-agent.pid',\n";
echo "];\n";
?></code></pre>
                    </div>

                    <!-- API Endpoints Reference -->
                    <div>
                        <h3 class="text-lg font-medium mb-4">{{ __('API Endpoints') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            {{ __('Endpoint') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            {{ __('Method') }}
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            {{ __('Description') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">
                                            /api/agent/data
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            POST
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Send backup data to dashboard') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">
                                            /api/agent/register
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            POST
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Register agent with dashboard') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-gray-100">
                                            /api/agent/config
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            POST
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Get site configuration from dashboard') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTokenVisibility() {
            const input = document.getElementById('agent-token');
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function copyToken() {
            const input = document.getElementById('agent-token');
            input.type = 'text';
            input.select();
            document.execCommand('copy');
            input.type = 'password';

            // Show toast notification
            alert('{{ __("Token copied to clipboard") }}');
        }
    </script>
</x-app-layout>
