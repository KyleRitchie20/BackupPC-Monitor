<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Backup Status for ') . $site->name }}
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
                        <h3 class="text-lg font-medium">Backup Hosts</h3>
                        <div class="flex space-x-2">
                            <a href="{{ route('sites.show', $site) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                                Site Details
                            </a>
                            @if(Auth::user()->isAdmin())
                                <a href="{{ route('sites.downloadReport', $site) }}" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                                    Download Report
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($processedBackups->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 dark:text-gray-300">No backup data available for this site.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">State</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Full Backup Age</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Incremental Age</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Backup Size</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($processedBackups as $backup)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $backup['host_name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 rounded text-sm {{ $backup['stateClass'] }}">{{ $backup['stateDisplay'] }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap {{ $backup['fullBackupAgeClass'] }}">
                                                {{ $backup['fullBackupAge'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap {{ $backup['incrementalAgeClass'] }}">
                                                {{ $backup['incrementalAge'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                {{ $backup['sizeFormatted'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 rounded text-sm {{ $backup['statusClass'] }}">{{ $backup['status'] }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $backup['updated_at']->diffForHumans() }}
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
</x-app-layout>
