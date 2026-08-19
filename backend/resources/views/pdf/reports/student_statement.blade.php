<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; border-bottom: 2px solid #0369a1; padding-bottom: 10px; margin-bottom: 16px; }
    .header h1 { font-size: 18px; color: #0c4a6e; }
    .header p  { font-size: 11px; color: #555; }
    .student-info { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; }
    .student-info table { width: 100%; border: none; }
    .student-info td { padding: 3px 6px; border: none; font-size: 11px; }
    .student-info td:first-child { color: #0369a1; font-weight: bold; width: 150px; }
    .section-title { font-size: 12px; font-weight: bold; color: #0c4a6e; margin: 12px 0 5px; }
    table.inv-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.inv-table th { background: #0c4a6e; color: #fff; padding: 5px 7px; font-size: 10px; text-align: left; }
    table.inv-table td { padding: 4px 7px; border-bottom: 1px solid #e0f2fe; font-size: 10px; }
    table.inv-table tr:nth-child(even) td { background: #f0f9ff; }
    table.inv-table tr.paid td   { color: #15803d; }
    table.inv-table tr.unpaid td { color: #b91c1c; }
    table.inv-table tr.partial td{ color: #b45309; }
    .payment-sub { font-size: 9px; color: #0369a1; padding: 2px 7px 4px 18px; }
    .balance-summary { border-top: 2px solid #0369a1; margin-top: 16px; padding-top: 10px; }
    .balance-summary table { width: 320px; float: right; border-collapse: collapse; }
    .balance-summary td { padding: 4px 8px; font-size: 11px; }
    .balance-summary td:last-child { text-align: right; font-weight: bold; }
    .balance-summary tr.balance-row td { font-size: 13px; color: #0c4a6e; background: #e0f2fe; border-radius: 3px; }
    .signature { margin-top: 50px; display: flex; justify-content: space-between; font-size: 10px; color: #374151; }
    .sig-line { border-top: 1px solid #374151; width: 200px; padding-top: 4px; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php $student = $report['student']; @endphp

<div class="header">
    <h1>{{ $student['school'] ?? 'ShulePay' }}</h1>
    <p>Student Fee Statement</p>
    <p>Generated: {{ now()->format('d M Y H:i') }}</p>
</div>

<div class="student-info">
    <table>
        <tr>
            <td>Student Name</td>
            <td>{{ $student['full_name'] }}</td>
            <td>Admission No.</td>
            <td>{{ $student['admission_number'] }}</td>
        </tr>
        <tr>
            <td>Class</td>
            <td>{{ $student['school_class'] }}</td>
            <td>Academic Year</td>
            <td>{{ $student['academic_year'] }}</td>
        </tr>
    </table>
</div>

<div class="section-title">Invoice &amp; Payment History</div>
<table class="inv-table">
    <thead>
        <tr>
            <th>Invoice No.</th>
            <th>Due Date</th>
            <th>Term</th>
            <th>Gross (TZS)</th>
            <th>Paid (TZS)</th>
            <th>Balance (TZS)</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['invoices'] as $inv)
        <tr class="{{ $inv['status'] }}">
            <td>{{ $inv['invoice_number'] }}</td>
            <td>{{ $inv['due_date'] }}</td>
            <td>{{ $inv['term'] ?? '—' }}</td>
            <td>{{ number_format($inv['gross_cents'] / 100, 2) }}</td>
            <td>{{ number_format($inv['paid_cents'] / 100, 2) }}</td>
            <td>{{ number_format($inv['balance_cents'] / 100, 2) }}</td>
            <td>{{ strtoupper($inv['status']) }}</td>
        </tr>
        @if(count($inv['payments']) > 0)
        <tr>
            <td colspan="7" style="padding: 0; border: none;">
                @foreach($inv['payments'] as $p)
                <div class="payment-sub">
                    &rarr; {{ $p['paid_at'] }} | {{ strtoupper($p['method']) }} | TZS {{ number_format($p['amount_cents'] / 100, 2) }}
                    @if($p['reference_number'])
                     | Ref: {{ $p['reference_number'] }}
                    @endif
                </div>
                @endforeach
            </td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>

<div class="balance-summary clearfix">
    <table>
        <tr>
            <td>Total Invoiced</td>
            <td>TZS {{ number_format($report['total_invoiced_cents'] / 100, 2) }}</td>
        </tr>
        <tr>
            <td>Total Paid</td>
            <td>TZS {{ number_format($report['total_paid_cents'] / 100, 2) }}</td>
        </tr>
        <tr class="balance-row">
            <td>Outstanding Balance</td>
            <td>TZS {{ number_format($report['balance_cents'] / 100, 2) }}</td>
        </tr>
    </table>
</div>

<div class="signature" style="margin-top: 60px;">
    <div>
        <div class="sig-line">Accountant Signature &amp; Stamp</div>
    </div>
    <div>
        <div class="sig-line">Parent / Guardian Signature</div>
    </div>
</div>

<div class="footer">ShulePay &mdash; Fee Management System &mdash; This is a computer-generated statement.</div>
</body>
</html>
