<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BackupPC Monitor Report</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }
        .header-subtitle {
            font-size: 11px;
            color: #4b5563;
            margin-top: 3px;
        }
        .header-meta {
            text-align: right;
            font-size: 10px;
            color: #6b7280;
        }
        .badge-period {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }

        /* Stats Grid */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .stats-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }
        .stats-val {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2px;
        }
        .stats-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Section Title */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin: 14px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1.5px solid #cbd5e1;
        }
        table.data-table td {
            padding: 5px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #fbfcfe;
        }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 9999px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-good {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .type-full {
            font-weight: bold;
            color: #2563eb;
        }
        .type-incr {
            color: #64748b;
        }

        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <h1 class="header-title">BackupPC Central Monitoring Report</h1>
                    <div class="header-subtitle">
                        Client Sites Covered: <strong>{{ $sites->pluck('name')->implode(', ') }}</strong>
                    </div>
                </td>
                <td class="header-meta">
                    <div style="margin-bottom: 4px;">
                        <span class="badge-period">Period: {{ $date_range_label }}</span>
                    </div>
                    <div>Generated: {{ $generated_at }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Executive Summary Cards -->
    <table class="stats-table">
        <tr>
            <td style="width: 14%; padding-right: 5px;">
                <div class="stats-card">
                    <div class="stats-val">{{ $stats['total_runs'] }}</div>
                    <div class="stats-label">Total Backups</div>
                </div>
            </td>
            <td style="width: 14%; padding: 0 3px;">
                <div class="stats-card">
                    <div class="stats-val" style="color: #2563eb;">{{ $stats['full_runs'] }}</div>
                    <div class="stats-label">Full Backups</div>
                </div>
            </td>
            <td style="width: 14%; padding: 0 3px;">
                <div class="stats-card">
                    <div class="stats-val">{{ $stats['incremental_runs'] }}</div>
                    <div class="stats-label">Incremental</div>
                </div>
            </td>
            <td style="width: 14%; padding: 0 3px;">
                <div class="stats-card">
                    <div class="stats-val" style="color: #059669;">{{ $stats['success_rate'] }}%</div>
                    <div class="stats-label">Success Rate</div>
                </div>
            </td>
            <td style="width: 15%; padding: 0 3px;">
                <div class="stats-card">
                    <div class="stats-val">{{ $stats['total_size_formatted'] }}</div>
                    <div class="stats-label">Data Protected</div>
                </div>
            </td>
            <td style="width: 14%; padding: 0 3px;">
                <div class="stats-card">
                    <div class="stats-val" style="color: #059669;">{{ $stats['healthy_hosts'] }}/{{ $stats['total_hosts'] }}</div>
                    <div class="stats-label">Healthy Hosts</div>
                </div>
            </td>
            <td style="width: 15%; padding-left: 5px;">
                <div class="stats-card">
                    <div class="stats-val" style="color: {{ ($stats['warning_hosts'] + $stats['critical_hosts']) > 0 ? '#dc2626' : '#059669' }};">
                        {{ $stats['warning_hosts'] + $stats['critical_hosts'] }}
                    </div>
                    <div class="stats-label">Hosts with Issues</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Host SLA Status Table -->
    <div class="section-title">Host SLA Status & Recent Activity</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 18%;">Site</th>
                <th style="width: 18%;">Host Name</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 11%;">Runs in Period</th>
                <th style="width: 14%;">Last Run in Range</th>
                <th style="width: 11%;">Full Age</th>
                <th style="width: 10%;">Incr Age</th>
                <th style="width: 8%;">Size</th>
            </tr>
        </thead>
        <tbody>
            @forelse($hosts as $host)
                <tr>
                    <td><strong>{{ $host['site_name'] }}</strong></td>
                    <td>{{ $host['host_name'] }}</td>
                    <td>
                        <span class="status-pill {{ $host['status_class'] }}">
                            {{ $host['status'] }}
                        </span>
                    </td>
                    <td>{{ $host['runs_in_period'] }}</td>
                    <td>{{ $host['last_run_in_period'] }}</td>
                    <td class="{{ $host['full_backup_age_class'] }}">{{ $host['full_backup_age'] }}</td>
                    <td class="{{ $host['incremental_age_class'] }}">{{ $host['incremental_age'] }}</td>
                    <td>{{ $host['last_size_formatted'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 12px; color: #94a3b8;">
                        No backup hosts configured or matching filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Backup Runs Log -->
    @if(count($runs) > 0)
        @if(count($hosts) > 10)
            <div class="page-break"></div>
        @endif
        <div class="section-title">Completed Backup Runs in Selected Period ({{ count($runs) }} total)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 16%;">Completed At</th>
                    <th style="width: 18%;">Site</th>
                    <th style="width: 20%;">Host Name</th>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 10%;">Duration</th>
                    <th style="width: 12%;">Size</th>
                    <th style="width: 14%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($runs->take(40) as $run)
                    <tr>
                        <td>{{ $run->completed_at ? $run->completed_at->format('Y-m-d H:i') : 'N/A' }}</td>
                        <td>{{ $run->site->name ?? 'Site ' . $run->site_id }}</td>
                        <td><strong>{{ $run->host_name }}</strong></td>
                        <td>
                            <span class="{{ $run->type === 'full' ? 'type-full' : 'type-incr' }}">
                                {{ strtoupper($run->type) }}
                            </span>
                        </td>
                        <td>{{ $run->formatted_duration }}</td>
                        <td>{{ $run->formatted_size }}</td>
                        <td>
                            <span class="status-pill {{ $run->status === 'success' ? 'status-good' : ($run->status === 'warning' ? 'status-warning' : 'status-danger') }}">
                                {{ strtoupper($run->status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(count($runs) > 40)
            <div style="font-size: 8.5px; color: #64748b; font-style: italic; margin-top: -10px; margin-bottom: 12px;">
                Showing first 40 of {{ count($runs) }} runs. Export CSV for the full audit log.
            </div>
        @endif
    @endif

    <div class="footer">
        BackupPC Monitor • Generated on {{ $generated_at }} • Page 1 of 1
    </div>
</body>
</html>
