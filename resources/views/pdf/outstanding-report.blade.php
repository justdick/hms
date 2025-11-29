<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Outstanding Balances Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #1a1a1a;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .report-title {
            text-align: center;
            margin: 15px 0;
        }
        .report-title h2 {
            margin: 0;
            font-size: 14px;
            color: #333;
        }
        .report-title .date {
            font-size: 10px;
            color: #666;
        }
        .summary-box {
            background: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-item {
            display: table-cell;
            text-align: center;
            padding: 5px;
        }
        .summary-item .label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-item .value {
            font-size: 12px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            font-size: 9px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .amount {
            font-family: monospace;
        }
        .total-row {
            background: #f5f5f5;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .aging-header {
            background: #e8e8e8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital['name'] }}</h1>
        @if($hospital['address'])
            <p>{{ $hospital['address'] }}</p>
        @endif
        @if($hospital['phone'])
            <p>Tel: {{ $hospital['phone'] }}</p>
        @endif
    </div>

    <div class="report-title">
        <h2>Outstanding Balances Report</h2>
        <p class="date">Generated: {{ $generated_at }}</p>
    </div>

    <div class="summary-box">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Total Outstanding</div>
                <div class="value">GHS {{ number_format($summary['total_outstanding'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Patients</div>
                <div class="value">{{ $summary['patient_count'] }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Current (0-30)</div>
                <div class="value">GHS {{ number_format($summary['aging_totals']['current'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">31-60 Days</div>
                <div class="value">GHS {{ number_format($summary['aging_totals']['days_30'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">61-90 Days</div>
                <div class="value">GHS {{ number_format($summary['aging_totals']['days_60'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="label">90+ Days</div>
                <div class="value">GHS {{ number_format($summary['aging_totals']['days_90_plus'], 2) }}</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Patient #</th>
                <th>Patient Name</th>
                <th>Insurance</th>
                <th class="text-right">Total</th>
                <th class="text-right aging-header">Current</th>
                <th class="text-right aging-header">31-60</th>
                <th class="text-right aging-header">61-90</th>
                <th class="text-right aging-header">90+</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balances as $balance)
                <tr>
                    <td>{{ $balance['patient_number'] }}</td>
                    <td>{{ $balance['patient_name'] }}</td>
                    <td>{{ $balance['has_insurance'] ? $balance['insurance_provider'] : 'None' }}</td>
                    <td class="text-right amount">{{ number_format($balance['total_outstanding'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($balance['aging']['current'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($balance['aging']['days_30'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($balance['aging']['days_60'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($balance['aging']['days_90_plus'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">No outstanding balances found</td>
                </tr>
            @endforelse
        </tbody>
        @if($balances->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL</strong></td>
                    <td class="text-right amount">{{ number_format($summary['total_outstanding'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($summary['aging_totals']['current'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($summary['aging_totals']['days_30'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($summary['aging_totals']['days_60'], 2) }}</td>
                    <td class="text-right amount">{{ number_format($summary['aging_totals']['days_90_plus'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        <p>This report was generated automatically by {{ $hospital['name'] }} Billing System</p>
        <p>Report Date: {{ $generated_at }}</p>
    </div>
</body>
</html>
