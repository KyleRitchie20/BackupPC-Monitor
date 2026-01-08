<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4a5568;
        }
        .header h1 {
            margin: 0;
            color: #2d3748;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            color: #718096;
        }
        .summary {
            background-color: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .summary h2 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #2d3748;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #2b6cb0;
        }
        .summary-item .label {
            font-size: 12px;
            color: #718096;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #4a5568;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:hover {
            background-color: #f7fafc;
        }
        .status-good {
            color: #38a169;
            font-weight: bold;
        }
        .status-warning {
            color: #d69e2e;
            font-weight: bold;
        }
        .status-danger {
            color: #e53e3e;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #718096;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>BackupPC Monitor - Weekly Backup Report</h1>
        <p>Generated on {{ $generatedAt }}</p>
        <p>Site: {{ $site->name }}</p>
    </div>

    <div class="summary">
        <h2>Summary</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="value">{{ $stats['totalClients'] }}</div>
                <div class="label">Total Clients</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ $stats['healthyClients'] }}</div>
                <div class="label">Healthy</div>
            </div>
            <div class="summary-item">
                <div class="value">{{ $stats['warningClients'] }}</div>
                <div class="label">Warnings</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @if(isset($allProcessedData) && count($allProcessedData) > 0 && isset($allProcessedData[0]['site_name']))
                <th>Site</th>
                @endif
                <th>Hostname</th>
                <th>Full Backup Age (days)</th>
                <th>Incremental Age (days)</th>
                <th>Total Size</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($processedData as $client)
            <tr>
                @if(isset($client['site_name']))
                <td>{{ $client['site_name'] }}</td>
                @endif
                <td>{{ $client['host_name'] }}</td>
                <td class="{{ $client['fullBackupAgeClass'] }}">{{ $client['fullBackupAge'] }}</td>
                <td class="{{ $client['incrementalAgeClass'] }}">{{ $client['incrementalAge'] }}</td>
                <td>{{ $client['sizeFormatted'] }}</td>
                <td class="{{ $client['statusClass'] }}">{{ $client['status'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>This is an automated report from BackupPC Monitor.</p>
        <p>Full backup warning: > 14 days | Incremental backup warning: > 7 days</p>
    </div>
</body>
</html>
