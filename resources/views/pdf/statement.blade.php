<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Statement - {{ $patient['patient_number'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .container {
            padding: 20px 30px;
        }

        /* Header / Letterhead */
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .hospital-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .hospital-details {
            font-size: 9pt;
            color: #666;
        }

        /* Statement Title */
        .statement-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: #1e40af;
            margin: 15px 0;
            padding: 10px;
            background-color: #eff6ff;
            border-radius: 4px;
        }

        /* Info Sections */
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px;
            margin-right: 10px;
        }

        .info-box:last-child {
            margin-right: 0;
        }

        .info-label {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 10pt;
            font-weight: 600;
            color: #1e293b;
        }

        /* Tables */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e40af;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        th.text-right {
            text-align: right;
        }

        td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9pt;
        }

        td.text-right {
            text-align: right;
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }

        .amount {
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 600;
        }

        .amount-positive {
            color: #16a34a;
        }

        .amount-negative {
            color: #dc2626;
        }

        /* Summary Box */
        .summary-box {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .summary-label {
            display: table-cell;
            width: 70%;
            font-size: 10pt;
            color: #475569;
        }

        .summary-value {
            display: table-cell;
            width: 30%;
            text-align: right;
            font-size: 10pt;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        .summary-total {
            border-top: 2px solid #1e40af;
            padding-top: 10px;
            margin-top: 10px;
        }

        .summary-total .summary-label {
            font-weight: bold;
            font-size: 11pt;
            color: #1e40af;
        }

        .summary-total .summary-value {
            font-weight: bold;
            font-size: 12pt;
            color: #1e40af;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
        }

        .generated-at {
            margin-top: 5px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-owing {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
            font-style: italic;
        }

        /* Page Break */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Hospital Letterhead -->
        <div class="header">
            <div class="hospital-name">{{ $hospital['name'] }}</div>
            <div class="hospital-details">
                @if($hospital['address'])
                    {{ $hospital['address'] }}<br>
                @endif
                @if($hospital['phone'])
                    Tel: {{ $hospital['phone'] }}
                @endif
                @if($hospital['email'])
                    | Email: {{ $hospital['email'] }}
                @endif
            </div>
        </div>

        <!-- Statement Title -->
        <div class="statement-title">
            PATIENT STATEMENT
        </div>

        <!-- Patient & Statement Info -->
        <div class="info-row">
            <div class="info-col">
                <div class="info-box">
                    <div class="info-label">Patient Name</div>
                    <div class="info-value">{{ $patient['name'] }}</div>
                    <div style="margin-top: 8px;">
                        <div class="info-label">Patient Number</div>
                        <div class="info-value">{{ $patient['patient_number'] }}</div>
                    </div>
                    @if($patient['phone_number'])
                    <div style="margin-top: 8px;">
                        <div class="info-label">Phone</div>
                        <div class="info-value">{{ $patient['phone_number'] }}</div>
                    </div>
                    @endif
                </div>
            </div>
            <div class="info-col">
                <div class="info-box">
                    <div class="info-label">Statement Period</div>
                    <div class="info-value">{{ $statement_period['start_date'] }} - {{ $statement_period['end_date'] }}</div>
                    <div style="margin-top: 8px;">
                        <div class="info-label">Generated On</div>
                        <div class="info-value">{{ $generated_at }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charges Table -->
        <div class="section-title">Charges</div>
        @if(count($charges) > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 15%;">Service Type</th>
                    <th style="width: 35%;">Description</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 12%;" class="text-right">Amount</th>
                    <th style="width: 13%;" class="text-right">Insurance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($charges as $charge)
                <tr>
                    <td>{{ $charge['date'] }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $charge['service_type'])) }}</td>
                    <td>{{ $charge['description'] }}</td>
                    <td>
                        <span class="status-badge status-{{ $charge['status'] }}">
                            {{ ucfirst($charge['status']) }}
                        </span>
                    </td>
                    <td class="text-right amount">GHS {{ number_format($charge['amount'], 2) }}</td>
                    <td class="text-right amount amount-positive">
                        @if($charge['insurance_covered_amount'] > 0)
                            GHS {{ number_format($charge['insurance_covered_amount'], 2) }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No charges found for this period</div>
        @endif

        <!-- Payments Table -->
        <div class="section-title">Payments</div>
        @if(count($payments) > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 20%;">Receipt Number</th>
                    <th style="width: 35%;">Description</th>
                    <th style="width: 15%;">Method</th>
                    <th style="width: 15%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment['date'] }}</td>
                    <td>{{ $payment['receipt_number'] ?? '-' }}</td>
                    <td>{{ $payment['description'] }}</td>
                    <td>{{ ucfirst($payment['payment_method']) }}</td>
                    <td class="text-right amount amount-positive">GHS {{ number_format($payment['paid_amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No payments found for this period</div>
        @endif

        <!-- Balance Summary -->
        <div class="summary-box">
            <div class="section-title" style="margin-top: 0; border-bottom: none;">Account Summary</div>
            
            <div class="summary-row">
                <div class="summary-label">Opening Balance</div>
                <div class="summary-value {{ $summary['opening_balance'] > 0 ? 'amount-negative' : '' }}">
                    GHS {{ number_format($summary['opening_balance'], 2) }}
                </div>
            </div>
            
            <div class="summary-row">
                <div class="summary-label">Total Charges (This Period)</div>
                <div class="summary-value">GHS {{ number_format($summary['total_charges'], 2) }}</div>
            </div>
            
            <div class="summary-row">
                <div class="summary-label">Total Payments (This Period)</div>
                <div class="summary-value amount-positive">- GHS {{ number_format($summary['total_paid'], 2) }}</div>
            </div>
            
            @if($summary['total_insurance_covered'] > 0)
            <div class="summary-row">
                <div class="summary-label">Insurance Coverage (This Period)</div>
                <div class="summary-value amount-positive">- GHS {{ number_format($summary['total_insurance_covered'], 2) }}</div>
            </div>
            @endif
            
            <div class="summary-row summary-total">
                <div class="summary-label">Closing Balance</div>
                <div class="summary-value {{ $summary['closing_balance'] > 0 ? 'amount-negative' : 'amount-positive' }}">
                    GHS {{ number_format($summary['closing_balance'], 2) }}
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated statement and does not require a signature.</p>
            <p class="generated-at">Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
