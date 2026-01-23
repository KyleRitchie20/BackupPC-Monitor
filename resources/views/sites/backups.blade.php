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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Disabled</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Exclude from Report</th>
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
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if(Auth::user()->isAdmin())
                                                    <input type="checkbox"
                                                           data-backup-id="{{ $backup['id'] }}"
                                                           data-site-id="{{ $site->id }}"
                                                           {{ $backup['disabled'] ?? false ? 'checked' : '' }}
                                                           disabled>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if(Auth::user()->isAdmin())
                                                    <input type="checkbox" class="exclude-from-report-checkbox"
                                                           data-backup-id="{{ $backup['id'] }}"
                                                           data-site-id="{{ $site->id }}"
                                                           {{ $backup['exclude_from_report'] ?? false ? 'checked' : '' }}>
                                                @endif
                                            </td>
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

    @push('scripts')
        <style>
            .loading-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid rgba(0, 0, 0, 0.1);
                border-radius: 50%;
                border-top-color: #3b82f6;
                animation: spin 1s ease-in-out infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            .success-message {
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: #10b981;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                z-index: 1000;
                animation: slideIn 0.3s ease-out;
            }
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Add event listeners to all "exclude from report" checkboxes
                document.querySelectorAll('.exclude-from-report-checkbox').forEach(function(checkbox) {
                    checkbox.addEventListener('change', function() {
                        const backupId = this.getAttribute('data-backup-id');
                        const siteId = this.getAttribute('data-site-id');
                        const isExcluded = this.checked;
                        const hostName = this.closest('tr').querySelector('td:nth-child(3)').textContent.trim();

                        // Store reference to the checkbox cell
                        const checkboxCell = this.parentNode;

                        // Show loading state with host name
                        checkboxCell.innerHTML = '<div class="loading-spinner" title="Updating..."></div>';

                        // Send AJAX request
                        fetch(`/sites/${siteId}/backups/toggle-exclude-from-report`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                backup_id: backupId,
                                exclude_from_report: isExcluded
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(err => { throw new Error(err.message || 'Network response was not ok'); });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Create new checkbox with updated state
                                const newCheckbox = document.createElement('input');
                                newCheckbox.type = 'checkbox';
                                newCheckbox.className = 'exclude-from-report-checkbox';
                                newCheckbox.setAttribute('data-backup-id', backupId);
                                newCheckbox.setAttribute('data-site-id', siteId);
                                newCheckbox.checked = data.exclude_from_report;

                                // Add event listener to the new checkbox
                                newCheckbox.addEventListener('change', arguments.callee);

                                // Replace the loading spinner with the updated checkbox
                                checkboxCell.innerHTML = '';
                                checkboxCell.appendChild(newCheckbox);

                                // Show success message
                                showSuccessMessage(`Host "${hostName}" report exclusion status updated successfully!`);
                            } else {
                                throw new Error(data.message || 'Failed to update report exclusion status');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Restore original checkbox state
                            const newCheckbox = document.createElement('input');
                            newCheckbox.type = 'checkbox';
                            newCheckbox.className = 'exclude-from-report-checkbox';
                            newCheckbox.setAttribute('data-backup-id', backupId);
                            newCheckbox.setAttribute('data-site-id', siteId);
                            newCheckbox.checked = !isExcluded; // Restore original state

                            // Add event listener to the new checkbox
                            newCheckbox.addEventListener('change', arguments.callee);

                            checkboxCell.innerHTML = '';
                            checkboxCell.appendChild(newCheckbox);

                            showErrorMessage(`Error updating host "${hostName}" report exclusion status: ${error.message}`);
                        });
                    });
                });

                // Helper function to show success messages
                function showSuccessMessage(message) {
                    const messageElement = document.createElement('div');
                    messageElement.className = 'success-message';
                    messageElement.textContent = message;

                    // Remove any existing messages
                    const existingMessages = document.querySelectorAll('.success-message, .error-message');
                    existingMessages.forEach(msg => msg.remove());

                    document.body.appendChild(messageElement);

                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        messageElement.style.opacity = '0';
                        messageElement.style.transform = 'translateX(100%)';
                        setTimeout(() => messageElement.remove(), 300);
                    }, 5000);
                }

                // Helper function to show error messages
                function showErrorMessage(message) {
                    const messageElement = document.createElement('div');
                    messageElement.className = 'success-message';
                    messageElement.style.backgroundColor = '#ef4444';
                    messageElement.textContent = message;

                    // Remove any existing messages
                    const existingMessages = document.querySelectorAll('.success-message, .error-message');
                    existingMessages.forEach(msg => msg.remove());

                    document.body.appendChild(messageElement);

                    // Auto-remove after 8 seconds
                    setTimeout(() => {
                        messageElement.style.opacity = '0';
                        messageElement.style.transform = 'translateX(100%)';
                        setTimeout(() => messageElement.remove(), 300);
                    }, 8000);
                }
            });
        </script>
    @endpush
</x-app-layout>
