<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Revenue Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .hospital-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .hospital-info {
            font-size: 10px;
            color: #666;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }
        .period {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .summary-section {
            margin-bottom: 20px;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .summary-row {
            display: table-row;
        }
        .summary-card {
            display: table-cell;
            width: 25%;
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .summary-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        .summary-value.positive {
            color: #16a34a;
        }
        .summary-value.negative {
            color: #dc2626;
        }
        .comparison-section {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 4px;
        }
        .comparison-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .comparison-text {
            font-size: 9px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        td {
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
            text-align: center;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .total-row {
            font-weight: bold;
            background-color: #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="hospital-name">{{ $hospital['name'] }}</div>
        @if($hospital['address'])
            <div class="hospital-info">{{ $hospital['address'] }}</div>
        @endif
        @if($hospital['phone'])
            <div class="hospital-info">Tel: {{ $hospital['phone'] }}</div>
        @endif
        <div class="report-title">Revenue Report</div>
        <div class="period">
            Period: {{ $report['summary']['period_start'] }} to {{ $report['summary']['period_end'] }}
        </div>
    </div>

    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-card">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value">GHS {{ number_format($report['summary']['total_revenue'], 2) }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Transactions</div>
                    <div class="summary-value">{{ number_format($report['summary']['transaction_count']) }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Average Transaction</div>
                    <div class="summary-value">GHS {{ number_format($report['summary']['average_transaction'], 2) }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Change vs Previous</div>
                    <div class="summary-value {{ $report['comparison']['percentage_change'] >= 0 ? 'positive' : 'negative' }}">
                        {{ $report['comparison']['percentage_change'] >= 0 ? '+' : '' }}{{ number_format($report['comparison']['percentage_change'], 1) }}%
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="comparison-section">
        <div class="comparison-title">Period Comparison</div>
        <div class="comparison-text">
            Previous Period ({{ $report['comparison']['previous_period_start'] }} to {{ $report['comparison']['previous_period_end'] }}):
            GHS {{ number_format($report['comparison']['previous_total'], 2) }} ({{ number_format($report['comparison']['previous_count']) }} transactions)
        </div>
    </div>

    <div class="section-title">Revenue by {{ $group_by_label }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ $group_by_label }}</th>
                <th class="text-right">Total Revenue</th>
                <th class="text-center">Transactions</th>
                <th class="text-right">Average</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; $grandCount = 0; @endphp
            @foreach($report['grouped_data'] as $item)
                @php
                    $grandTotal += $item['total'];
                    $grandCount += $item['count'];
                @endphp
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td class="text-right">GHS {{ number_format($item['total'], 2) }}</td>
                    <td class="text-center">{{ number_format($item['count']) }}</td>
                    <td class="text-right">GHS {{ number_format($item['average'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">GHS {{ number_format($grandTotal, 2) }}</td>
                <td class="text-center">{{ number_format($grandCount) }}</td>
                <td class="text-right">GHS {{ $grandCount > 0 ? number_format($grandTotal / $grandCount, 2) : '0.00' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ $generated_at }} | This is a computer-generated document
    </div>
</body>
</html>
