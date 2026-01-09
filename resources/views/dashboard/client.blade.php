<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Your Backup Status</h3>
            <div class="flex items-center space-x-4">
                <button id="theme-toggle" class="p-2 rounded-full bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500">
                    <svg id="theme-toggle-dark-icon" class="h-5 w-5 text-gray-800 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg id="theme-toggle-light-icon" class="h-5 w-5 text-gray-800 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>
            </div>
        </div>

        @php
            use App\Models\Site;
            use App\Services\BackupPCService;

            $site = Site::find(Auth::user()->site_id);
            $backupService = new BackupPCService();
            $summary = $site ? $backupService->getBackupStatusSummary($site) : null;
        @endphp

        @if(!$site)
            <div class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded">
                <p>No site assigned to your account. Please contact an administrator.</p>
            </div>
        @elseif(empty($summary['hosts']))
            <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded">
                <p>No backup data available. The system is fetching data from your BackupPC server.</p>
                <p class="mt-2">You can manually trigger a data fetch or wait for the next scheduled update.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Total Hosts -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-300">Total Hosts</div>
                    <div class="text-2xl font-bold">{{ $summary['total_hosts'] }}</div>
                </div>

                <!-- Successful Backups -->
                <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                    <div class="text-sm text-green-600 dark:text-green-300">Successful Backups</div>
                    <div class="text-2xl font-bold">{{ $summary['successful_backups'] }}</div>
                </div>

                <!-- Failed Backups -->
                <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg">
                    <div class="text-sm text-red-600 dark:text-red-300">Failed Backups</div>
                    <div class="text-2xl font-bold">{{ $summary['failed_backups'] }}</div>
                </div>

                <!-- Disk Usage -->
                <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                    <div class="text-sm text-blue-600 dark:text-blue-300">Disk Usage</div>
                    <div class="text-2xl font-bold">{{ $summary['disk_usage'] }}%</div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-md font-medium mb-2">Backup Status Overview</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Backup</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Error</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($summary['hosts'] as $host)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $host['name'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(str_contains($host['state'], 'backup_in_progress'))
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-sm">In Progress</span>
                                        @elseif(str_contains($host['state'], 'idle') && $host['error'])
                                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">Failed</span>
                                        @elseif(str_contains($host['state'], 'idle'))
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">Success</span>
                                        @else
                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">{{ $host['state'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $host['last_backup'] ? \Carbon\Carbon::parse($host['last_backup'])->diffForHumans() : 'Never' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $host['error'] ? Str::limit($host['error'], 50) : 'None' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-md font-medium mb-2">Server Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-300 mb-2">BackupPC Server</div>
                        <div class="font-medium">{{ $site->backuppc_url }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-300 mt-1">Connection: {{ ucfirst($site->connection_method) }}</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-300 mb-2">Next Data Refresh</div>
                        <div class="font-medium">{{ $site->polling_interval }} minutes</div>
                        <div class="text-sm text-gray-500 dark:text-gray-300 mt-1">Last updated: {{ \App\Models\BackupData::where('site_id', $site->id)->max('updated_at') ?? 'Never' }}</div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button onclick="fetchBackupData()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    Refresh Data Now
                </button>
            </div>
        @endif
    </div>
</div>

<script>
    function fetchBackupData() {
        if (!confirm('Are you sure you want to manually fetch backup data? This may take a moment.')) {
            return;
        }

        fetch('/fetch-backup-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ site_id: {{ Auth::user()->site_id }} })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Backup data fetch initiated successfully! The page will refresh shortly.');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                alert('Failed to initiate backup data fetch: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while trying to fetch backup data.');
        });
    }

    // Theme toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('theme-toggle');
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');

        if (!themeToggle || !darkIcon || !lightIcon) {
            console.error('Theme toggle elements not found');
            return;
        }

        // Check for saved theme preference or use system preference
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // Set initial theme
        if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
            document.documentElement.classList.add('dark');
            darkIcon.classList.add('hidden');
            lightIcon.classList.remove('hidden');
        } else {
            document.documentElement.classList.remove('dark');
            darkIcon.classList.remove('hidden');
            lightIcon.classList.add('hidden');
        }

        // Toggle theme on button click
        themeToggle.addEventListener('click', function() {
            console.log('Theme toggle clicked');
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                darkIcon.classList.remove('hidden');
                lightIcon.classList.add('hidden');
                localStorage.setItem('theme', 'light');
                console.log('Switched to light theme');
            } else {
                document.documentElement.classList.add('dark');
                darkIcon.classList.add('hidden');
                lightIcon.classList.remove('hidden');
                localStorage.setItem('theme', 'dark');
                console.log('Switched to dark theme');
            }
        });

        // Watch for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                if (e.matches) {
                    document.documentElement.classList.add('dark');
                    darkIcon.classList.add('hidden');
                    lightIcon.classList.remove('hidden');
                } else {
                    document.documentElement.classList.remove('dark');
                    darkIcon.classList.remove('hidden');
                    lightIcon.classList.add('hidden');
                }
            }
        });
    });
</script>
