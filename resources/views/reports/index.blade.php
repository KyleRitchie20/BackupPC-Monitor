<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 leading-tight">
                    {{ __('Backup Reports & Analytics') }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Generate manual date-selected reports or manage automated scheduled reporting across client sites.
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('reports.download-pdf', request()->query()) }}" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </a>
                <a href="{{ route('reports.export-csv', request()->query()) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export CSV
                </a>
                <button onclick="document.getElementById('emailModal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email Report
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Flash Notifications -->
            @if (session('success'))
                <div class="p-4 bg-green-50 dark:bg-green-900/30 border-l-4 border-green-500 rounded-r-lg text-green-800 dark:text-green-200">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 bg-red-50 dark:bg-red-900/30 border-l-4 border-red-500 rounded-r-lg text-red-800 dark:text-red-200">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Report Filters and Date Selection Card -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                <form id="reportFilterForm" action="{{ route('reports.index') }}" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">

                        <!-- Site Selection -->
                        @if(Auth::user()->isAdmin())
                            <div class="md:col-span-3">
                                <label for="site_id" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-1">
                                    Client Site
                                </label>
                                <select id="site_id" name="site_id" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="all" {{ $selectedSiteId === 'all' ? 'selected' : '' }}>All Monitored Sites ({{ $sites->count() }})</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}" {{ (string)$selectedSiteId === (string)$site->id ? 'selected' : '' }}>
                                            {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-1">
                                    Client Site
                                </label>
                                <input type="text" value="{{ $sites->first()->name ?? 'My Site' }}" disabled class="w-full text-sm bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-lg">
                            </div>
                        @endif

                        <!-- Date Presets -->
                        <div class="md:col-span-5">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Quick Date Selection
                            </label>
                            <input type="hidden" name="preset" id="presetInput" value="{{ $datePreset }}">
                            <div class="flex flex-wrap gap-1.5">
                                @php
                                    $presets = [
                                        'today' => 'Today',
                                        'yesterday' => 'Yesterday',
                                        'last_7_days' => 'Last 7d',
                                        'last_14_days' => 'Last 14d',
                                        'last_30_days' => 'Last 30d',
                                        'this_month' => 'This Month',
                                        'last_month' => 'Last Month',
                                        'custom' => 'Custom'
                                    ];
                                @endphp
                                @foreach($presets as $key => $label)
                                    <button type="button" onclick="selectPreset('{{ $key }}')" class="px-2.5 py-1 text-xs rounded-md font-medium transition {{ $datePreset === $key ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Custom Date Pickers -->
                        <div class="md:col-span-2">
                            <label for="start_date" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Start Date
                            </label>
                            <input type="date" id="start_date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="md:col-span-2">
                            <label for="end_date" class="block text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider mb-1">
                                End Date
                            </label>
                            <input type="date" id="end_date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                    </div>

                    <div class="flex flex-wrap justify-between items-center pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex items-center space-x-4">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Filter Status:</span>
                            <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" name="status" value="all" {{ $statusFilter === 'all' ? 'checked' : '' }} onchange="this.form.submit()" class="text-blue-600 focus:ring-blue-500">
                                <span class="ml-1.5">All Hosts</span>
                            </label>
                            <label class="inline-flex items-center text-xs text-green-700 dark:text-green-400 cursor-pointer">
                                <input type="radio" name="status" value="healthy" {{ $statusFilter === 'healthy' ? 'checked' : '' }} onchange="this.form.submit()" class="text-green-600 focus:ring-green-500">
                                <span class="ml-1.5">Healthy Only</span>
                            </label>
                            <label class="inline-flex items-center text-xs text-red-700 dark:text-red-400 cursor-pointer">
                                <input type="radio" name="status" value="issues" {{ $statusFilter === 'issues' ? 'checked' : '' }} onchange="this.form.submit()" class="text-red-600 focus:ring-red-500">
                                <span class="ml-1.5">With Issues Only</span>
                            </label>
                        </div>
                        <div class="flex items-center space-x-2 mt-2 sm:mt-0">
                            <button type="submit" class="px-4 py-2 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 rounded-lg text-sm font-semibold hover:bg-gray-800 dark:hover:bg-white transition shadow-sm">
                                Apply & Preview
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Executive Summary Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Runs</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $reportData['stats']['total_runs'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">in selected window</div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-blue-600 dark:text-blue-400 uppercase tracking-wider">Full Backups</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $reportData['stats']['full_runs'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">completed</div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Incremental</div>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $reportData['stats']['incremental_runs'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">completed</div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Success Rate</div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $reportData['stats']['success_rate'] }}%</div>
                    <div class="text-xs text-gray-400 mt-1">{{ $reportData['stats']['successful_runs'] }} succeeded</div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data Protected</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $reportData['stats']['total_size_formatted'] }}</div>
                    <div class="text-xs text-gray-400 mt-1">cumulative size</div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Host Compliance</div>
                    <div class="text-2xl font-bold {{ $reportData['stats']['warning_hosts'] + $reportData['stats']['critical_hosts'] > 0 ? 'text-amber-500' : 'text-emerald-600' }} mt-1">
                        {{ $reportData['stats']['healthy_hosts'] }}/{{ $reportData['stats']['total_hosts'] }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">{{ $reportData['stats']['warning_hosts'] + $reportData['stats']['critical_hosts'] }} require review</div>
                </div>
            </div>

            <!-- Navigation Tabs for Report Sections -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="switchTab('hosts')" id="tab-btn-hosts" class="tab-btn border-blue-500 text-blue-600 dark:text-blue-400 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
                        Host SLA & Compliance ({{ count($reportData['hosts']) }})
                    </button>
                    <button onclick="switchTab('runs')" id="tab-btn-runs" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
                        Backup Runs Log ({{ count($reportData['runs']) }})
                    </button>
                    @if(Auth::user()->isAdmin())
                        <button onclick="switchTab('schedules')" id="tab-btn-schedules" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
                            Automated Schedules ({{ $schedules->count() }})
                        </button>
                    @endif
                </nav>
            </div>

            <!-- Tab 1: Host SLA Status -->
            <div id="tab-hosts" class="tab-content bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Host SLA Status</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Current compliance and backup activity during <strong>{{ $reportData['date_range_label'] }}</strong></p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Runs in Period</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Run in Range</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Full Age</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Incr Age</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @forelse($reportData['hosts'] as $host)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $host['site_name'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200 font-mono">
                                        {{ $host['host_name'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($host['status'] === 'Healthy')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                Healthy
                                            </span>
                                        @elseif($host['status'] === 'Warning')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                                Warning
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                Critical
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-semibold">{{ $host['runs_in_period'] }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $host['last_run_in_period'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $host['full_backup_age_class'] === 'status-danger' ? 'text-red-600' : ($host['full_backup_age_class'] === 'status-warning' ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $host['full_backup_age'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $host['incremental_age_class'] === 'status-danger' ? 'text-red-600' : ($host['incremental_age_class'] === 'status-warning' ? 'text-amber-600' : 'text-green-600') }}">
                                        {{ $host['incremental_age'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $host['last_size_formatted'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No hosts found matching your criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Backup Runs Log -->
            <div id="tab-runs" class="tab-content hidden bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Historical Backup Runs</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Individual backup operations completed between <strong>{{ $reportData['date_range_label'] }}</strong></p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Completed At</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Host</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @forelse($reportData['runs'] as $run)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100 font-mono">
                                        {{ $run->completed_at ? $run->completed_at->format('Y-m-d H:i:s') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $run->site->name ?? 'Site ' . $run->site_id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $run->host_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $run->type === 'full' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                            {{ strtoupper($run->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $run->formatted_duration }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-gray-200">
                                        {{ $run->formatted_size }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($run->status === 'success')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                SUCCESS
                                            </span>
                                        @elseif($run->status === 'warning')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                                WARNING
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                FAILED
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No backup runs recorded in the selected date range. As agents report or data is fetched, runs will accumulate here automatically.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Automated Report Schedules (Admin Only) -->
            @if(Auth::user()->isAdmin())
                <div id="tab-schedules" class="tab-content hidden bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Automated Report Schedules</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Configure scheduled email dispatches with automatic date windows (daily, weekly, monthly).</p>
                        </div>
                        <button onclick="document.getElementById('createScheduleModal').classList.remove('hidden')" class="inline-flex items-center px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            New Schedule
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Schedule Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Site</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Frequency</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date Window</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Recipients</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Run</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @forelse($schedules as $schedule)
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $schedule->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                            {{ $schedule->site ? $schedule->site->name : 'All Sites' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 capitalize">
                                            {{ $schedule->frequency }} at {{ $schedule->time }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ str_replace('_', ' ', $schedule->date_range_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ implode(', ', $schedule->recipients ?? []) }}">
                                            {{ implode(', ', $schedule->recipients ?? []) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <form action="{{ route('reports.schedules.toggle', $schedule) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-0.5 rounded-full text-xs font-semibold transition {{ $schedule->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                                    {{ $schedule->is_active ? 'Active' : 'Paused' }}
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                            {{ $schedule->last_run_at ? $schedule->last_run_at->diffForHumans() : 'Never' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs space-x-2">
                                            <form action="{{ route('reports.schedules.run-now', $schedule) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded font-medium transition">
                                                    Run Now
                                                </button>
                                            </form>
                                            <form action="{{ route('reports.schedules.destroy', $schedule) }}" method="POST" class="inline" onsubmit="return confirm('Delete this schedule?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2.5 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded font-medium transition">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No automated report schedules configured yet. Click "New Schedule" to create one.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Modal: Email Report On Demand -->
    <div id="emailModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-6 border border-gray-100 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Email Backup Report</h3>
                <button type="button" onclick="document.getElementById('emailModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form action="{{ route('reports.email') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="site_id" value="{{ $selectedSiteId }}">
                <input type="hidden" name="preset" value="{{ $datePreset }}">
                <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Period</label>
                    <div class="text-sm text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 p-2 rounded-lg font-medium">
                        {{ $reportData['date_range_label'] }}
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Recipient Email</label>
                    <input type="email" id="email" name="email" required placeholder="admin@example.com" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="format" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Attachment Format</label>
                    <select id="format" name="format" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="pdf">PDF Document (.pdf)</option>
                        <option value="csv">CSV Spreadsheet (.csv)</option>
                        <option value="both">Both (PDF and CSV)</option>
                    </select>
                </div>

                <div>
                    <label for="subject" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Subject (Optional)</label>
                    <input type="text" id="subject" name="subject" placeholder="BackupPC Report — {{ $reportData['date_range_label'] }}" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="document.getElementById('emailModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">Send Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Create Automated Report Schedule -->
    @if(Auth::user()->isAdmin())
        <div id="createScheduleModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-100 dark:border-gray-700">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Create Automated Report Schedule</h3>
                    <button type="button" onclick="document.getElementById('createScheduleModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <form action="{{ route('reports.schedules.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="sched_name" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Schedule Name</label>
                        <input type="text" id="sched_name" name="name" required placeholder="Weekly Client Backup Audit" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="sched_site_id" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Target Site</label>
                            <select id="sched_site_id" name="site_id" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All Sites</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="sched_frequency" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Frequency</label>
                            <select id="sched_frequency" name="frequency" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="sched_window" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Date Window Covered</label>
                            <select id="sched_window" name="date_range_type" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="last_7_days" selected>Preceding 7 Days</option>
                                <option value="last_14_days">Preceding 14 Days</option>
                                <option value="last_30_days">Preceding 30 Days</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="last_month">Full Previous Month</option>
                            </select>
                        </div>
                        <div>
                            <label for="sched_time" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Delivery Time</label>
                            <input type="time" id="sched_time" name="time" value="08:00" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="sched_format" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Report Format</label>
                            <select id="sched_format" name="format" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="pdf" selected>PDF</option>
                                <option value="csv">CSV</option>
                                <option value="both">Both (PDF & CSV)</option>
                            </select>
                        </div>
                        <div>
                            <label for="sched_day" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Day (if weekly)</label>
                            <select id="sched_day" name="day_of_week" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="0">Sunday</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="sched_recipients" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase mb-1">Recipients (comma or newline separated)</label>
                        <textarea id="sched_recipients" name="recipients" rows="2" required placeholder="client@company.com, tech@msp.com" class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" onclick="document.getElementById('createScheduleModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">Save Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        function selectPreset(preset) {
            document.getElementById('presetInput').value = preset;
            const today = new Date();
            const formatDate = (d) => d.toISOString().split('T')[0];

            let start = new Date();
            let end = new Date();

            switch (preset) {
                case 'today':
                    break;
                case 'yesterday':
                    start.setDate(today.getDate() - 1);
                    end.setDate(today.getDate() - 1);
                    break;
                case 'last_7_days':
                    start.setDate(today.getDate() - 7);
                    break;
                case 'last_14_days':
                    start.setDate(today.getDate() - 14);
                    break;
                case 'last_30_days':
                    start.setDate(today.getDate() - 30);
                    break;
                case 'this_month':
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case 'last_month':
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'custom':
                    return; // user will edit inputs directly
            }

            document.getElementById('start_date').value = formatDate(start);
            document.getElementById('end_date').value = formatDate(end);
            document.getElementById('reportFilterForm').submit();
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            const targetTab = document.getElementById('tab-' + tabName);
            const targetBtn = document.getElementById('tab-btn-' + tabName);

            if (targetTab && targetBtn) {
                targetTab.classList.remove('hidden');
                targetBtn.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                targetBtn.classList.remove('border-transparent', 'text-gray-500');
            }
        }
    </script>
</x-app-layout>
