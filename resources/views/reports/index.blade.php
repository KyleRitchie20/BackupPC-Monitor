<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('success'))
                        <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium">System Overview</h3>
                        <a href="{{ route('download.report') }}" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            Download All Sites Report
                        </a>
                    </div>

                    @php
                        use App\Models\BackupData;
                        use App\Services\BackupPCService;

                        $backupService = new BackupPCService();
                        $totalHosts = 0;
                        $totalSuccessful = 0;
                        $totalFailed = 0;
                        $totalDiskUsage = 0;
                        $siteCount = $sites->count();

                        foreach ($sites as $site) {
                            $summary = $backupService->getBackupStatusSummary($site);
                            $totalHosts += $summary['total_hosts'];
                            $totalSuccessful += $summary['successful_backups'];
                            $totalFailed += $summary['failed_backups'];
                            $totalDiskUsage += $summary['disk_usage'];
                        }

                        $averageDiskUsage = $siteCount > 0 ? round($totalDiskUsage / $siteCount) : 0;
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <!-- Total Sites -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 dark:text-gray-300">Total Sites</div>
                            <div class="text-2xl font-bold">{{ $siteCount }}</div>
                        </div>

                        <!-- Total Hosts -->
                        <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                            <div class="text-sm text-blue-600 dark:text-blue-300">Total Hosts</div>
                            <div class="text-2xl font-bold">{{ $totalHosts }}</div>
                        </div>

                        <!-- Successful Backups -->
                        <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                            <div class="text-sm text-green-600 dark:text-green-300">Successful Backups</div>
                            <div class="text-2xl font-bold">{{ $totalSuccessful }}</div>
                        </div>

                        <!-- Failed Backups -->
                        <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg">
                            <div class="text-sm text-red-600 dark:text-red-300">Failed Backups</div>
                            <div class="text-2xl font-bold">{{ $totalFailed }}</div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h4 class="text-md font-medium mb-2">Site Reports</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hosts</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Successful</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Failed</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($sites as $site)
                                        @php
                                            $summary = $backupService->getBackupStatusSummary($site);
                                            $hasAgent = !empty($site->agent_token);
                                            $agentActive = $hasAgent && $site->last_agent_contact && $site->last_agent_contact->gt(now()->subMinutes(10));
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ route('sites.backups', $site) }}" class="text-blue-600 hover:text-blue-900">
                                                    {{ $site->name }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $summary['total_hosts'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $summary['successful_backups'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $summary['failed_backups'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($hasAgent)
                                                    <span class="px-2 py-1 rounded text-sm {{ $agentActive ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                                        {{ $agentActive ? 'Agent Connected' : 'Agent Offline' }}
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 rounded text-sm {{ $site->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $site->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ route('sites.downloadReport', $site) }}" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                                    Download Report
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h4 class="text-md font-medium mb-2">System Health</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm">Average Disk Usage:</span>
                                    <span class="font-medium">{{ $averageDiskUsage }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm">Total Backup Success Rate:</span>
                                    <span class="font-medium">
                                        {{ $totalHosts > 0 ? round(($totalSuccessful / $totalHosts) * 100) : 0 }}%
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm">Total Backup Failure Rate:</span>
                                    <span class="font-medium">
                                        {{ $totalHosts > 0 ? round(($totalFailed / $totalHosts) * 100) : 0 }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h4 class="text-md font-medium mb-2">Quick Actions</h4>
                            <div class="space-y-2">
                                <a href="{{ route('sites.create') }}" class="block text-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                    Add New Site
                                </a>
                                <button onclick="fetchAllBackupData()" class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                                    Refresh All Sites Data
                                </button>
                                <a href="{{ route('sites.index') }}" class="block text-center px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">
                                    Manage Sites
                                </a>
                            </div>
                        </div>

                        <!-- User Management Card -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <h4 class="text-md font-medium mb-2">User Management</h4>
                            <div class="space-y-2">
                                <a href="{{ route('users.index') }}" class="block w-full text-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Manage Users</a>
                                <a href="{{ route('users.create') }}" class="block w-full text-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">Add New User</a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h4 class="text-md font-medium mb-2">Recent Backup Activity</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            @php
                                $allBackups = BackupData::with('site')
                                    ->where('host_name', '!=', 'server')
                                    ->where('host_name', '!=', 'disk_usage')
                                    ->where('host_name', '!=', 'cpool')
                                    ->orderBy('updated_at', 'desc')
                                    ->get();

                                // Filter out disabled backups (check both disabled field and raw_data)
                                $recentBackups = $allBackups->filter(function ($backup) {
                                    $isDisabled = $backup->disabled ?? 0;
                                    if ($isDisabled === 0 && $backup->raw_data && isset($backup->raw_data['disabled'])) {
                                        $isDisabled = $backup->raw_data['disabled'];
                                    }
                                    return $isDisabled === 0;
                                })->take(10);
                            @endphp

                            @if($recentBackups->isEmpty())
                                <p class="text-gray-500 dark:text-gray-300">No recent backup activity found.</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-white dark:bg-gray-800">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-gray-50 dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                            @foreach($recentBackups as $backup)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">{{ $backup->site->name }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">{{ $backup->host_name }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @if(str_contains($backup->state, 'backup_in_progress'))
                                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-sm">In Progress</span>
                                                        @elseif(str_contains($backup->state, 'idle') && $backup->error_message)
                                                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-sm">Failed</span>
                                                        @elseif(str_contains($backup->state, 'idle'))
                                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">Success</span>
                                                        @else
                                                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-sm">{{ $backup->state }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        {{ $backup->updated_at->diffForHumans() }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    function fetchAllBackupData() {
        if (!confirm('Are you sure you want to manually fetch backup data for ALL sites? This may take a while.')) {
            return;
        }

        fetch('/fetch-all-backup-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Backup data fetch initiated for all sites! The page will refresh shortly.');
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
</script>