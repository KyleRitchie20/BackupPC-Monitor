<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Site Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-4">Site Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p><strong>Name:</strong> {{ $site->name }}</p>
                                <p><strong>Description:</strong> {{ $site->description ?? 'N/A' }}</p>
                                <p><strong>BackupPC URL:</strong> {{ $site->backuppc_url }}</p>
                                <p><strong>Connection Method:</strong> {{ ucfirst($site->connection_method) }}</p>
                            </div>
                            <div>
                                <p><strong>Polling Interval:</strong> {{ $site->polling_interval }} minutes</p>
                                <p><strong>Status:</strong>
                                    <span class="px-2 py-1 rounded text-sm {{ $site->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $site->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </p>
                                <p><strong>Created At:</strong> {{ $site->created_at->format('Y-m-d H:i:s') }}</p>
                                <p><strong>Updated At:</strong> {{ $site->updated_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        </div>
                    </div>

                    @if($site->connection_method === 'ssh')
                        <div class="mb-6">
                            <h3 class="text-lg font-medium mb-4">SSH Connection Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p><strong>SSH Host:</strong> {{ $site->ssh_host ?? 'N/A' }}</p>
                                    <p><strong>SSH Port:</strong> {{ $site->ssh_port ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p><strong>SSH Username:</strong> {{ $site->ssh_username ?? 'N/A' }}</p>
                                    <p><strong>SSH Password:</strong> ********</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mb-6">
                            <h3 class="text-lg font-medium mb-4">Polling Agent Details</h3>
                            <div>
                                <p><strong>API Key:</strong> ********</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('sites.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                            {{ __('Back to List') }}
                        </a>
                        @if(Auth::user()->isAdmin())
                            <a href="{{ route('sites.edit', $site) }}" class="ml-3 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                {{ __('Edit Site') }}
                            </a>
                            <a href="{{ route('sites.downloadReport', $site) }}" class="ml-3 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                                {{ __('Download Report') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>