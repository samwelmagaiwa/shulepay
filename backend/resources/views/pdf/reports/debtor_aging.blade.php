<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; border-bottom: 2px solid #dc2626; padding-bottom: 10px; margin-bottom: 16px; }
    .header h1 { font-size: 18px; color: #991b1b; }
    .header p  { font-size: 11px; color: #555; }
    .summary-grid { display: flex; gap: 10px; margin-bottom: 16px; }
    .summary-box { border: 1px solid #fecaca; border-radius: 4px; padding: 8px 14px; flex: 1; }
    .summary-box .label { font-size: 10px; color: #6b7280; }
    .summary-box .value { font-size: 15px; font-weight: bold; color: #b91c1c; }
    .bucket-header { font-size: 12px; font-weight: bold; padding: 6px 10px; color: #fff; margin: 14px 0 4px; border-radius: 3px; }
    .current    { background: #16a34a; }
    .days_1_30  { background: #ca8a04; }
    .days_31_60 { background: #ea580c; }
    .days_61_90 { background: #dc2626; }
    .over_90    { background: #7f1d1d; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th { background: #f3f4f6; color: #374151; text-align: left; padding: 5px 8px; font-size: 10px; }
    td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    tr:nth-child(even) td { background: #fafafa; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    .bucket-summary { font-size: 10px; color: #374151; margin-bottom: 4px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>Debtor Aging Report &mdash; As of {{ $report['summary']['as_of'] }}</p>
    <p>Generated: {{ now()->format('d M Y H:i') }}</p>
</div>

<div class="summary-grid">
    <div class="summary-box">
        <div class="label">Total Debtors</div>
        <div class="value">{{ number_format($report['summary']['total_debtors']) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">Total Outstanding</div>
        <div class="value">TZS {{ number_format($report['summary']['total_outstanding_cents'] / 100, 2) }}</div>
    </div>
</div>

@php
$bucketLabels = [
    'current'    => 'Current (Not Yet Due)',
    'days_1_30'  => '1–30 Days Overdue',
    'days_31_60' => '31–60 Days Overdue',
    'days_61_90' => '61–90 Days Overdue',
    'over_90'    => 'Over 90 Days Overdue',
];
@endphp

@foreach($report['buckets'] as $key => $bucket)
    <div class="bucket-header {{ $key }}">
        {{ $bucketLabels[$key] ?? $key }}
        &mdash; {{ $bucket['count'] }} students &mdash;
        TZS {{ number_format($bucket['amount_cents'] / 100, 2) }}
    </div>

    @if(count($bucket['students']) > 0)
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Admission No.</th>
                <th>Class</th>
                <th>Oldest Invoice Date</th>
                <th>Outstanding (TZS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bucket['students'] as $s)
            <tr>
                <td>{{ $s['full_name'] }}</td>
                <td>{{ $s['admission_number'] }}</td>
                <td>{{ $s['school_class'] }}</td>
                <td>{{ $s['oldest_invoice_date'] }}</td>
                <td>{{ number_format($s['outstanding_cents'] / 100, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p style="font-size:10px; color:#6b7280; padding: 4px 0 10px;">No students in this bucket.</p>
    @endif
@endforeach

<div class="footer">ShulePay &mdash; Fee Management System</div>
</body>
</html>
