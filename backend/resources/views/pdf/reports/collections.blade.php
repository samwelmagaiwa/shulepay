<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 16px; }
    .header h1 { font-size: 18px; color: #1e3a8a; }
    .header p  { font-size: 11px; color: #555; }
    .section-title { font-size: 13px; font-weight: bold; color: #1e3a8a; margin: 14px 0 6px; }
    .summary-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .summary-box { border: 1px solid #bfdbfe; border-radius: 4px; padding: 8px 14px; min-width: 130px; }
    .summary-box .label { font-size: 10px; color: #6b7280; }
    .summary-box .value { font-size: 15px; font-weight: bold; color: #1d4ed8; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th { background: #1e3a8a; color: #fff; text-align: left; padding: 6px 8px; font-size: 10px; }
    td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    tr:nth-child(even) td { background: #f0f4ff; }
    .total-row td { font-weight: bold; background: #dbeafe; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>{{ __('pdf.collections_title') }} &mdash; {{ __('pdf.period_range', ['from' => $report['period']['from'], 'to' => $report['period']['to']]) }}</p>
    <p>{{ __('pdf.generated') }}: {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</div>

<div class="section-title">{{ __('pdf.summary') }}</div>
<div class="summary-grid">
    <div class="summary-box">
        <div class="label">{{ __('pdf.total_payments') }}</div>
        <div class="value">{{ number_format($report['summary']['total_payments']) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">{{ __('pdf.total_collected') }}</div>
        <div class="value">{{ number_format($report['summary']['total_amount_cents'] / 100, 2) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">{{ __('pdf.invoices') }}</div>
        <div class="value">{{ number_format($report['summary']['invoice_count']) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">{{ __('pdf.paid') }}</div>
        <div class="value">{{ number_format($report['summary']['paid_count']) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">{{ __('pdf.partial') }}</div>
        <div class="value">{{ number_format($report['summary']['partial_count']) }}</div>
    </div>
    <div class="summary-box">
        <div class="label">{{ __('pdf.unpaid') }}</div>
        <div class="value">{{ number_format($report['summary']['unpaid_count']) }}</div>
    </div>
</div>

<div class="section-title">{{ __('pdf.collections_by_period') }}</div>
<table>
    <thead>
        <tr><th>{{ __('pdf.period') }}</th><th>{{ __('pdf.payments') }}</th><th>{{ __('pdf.amount_tzs') }}</th></tr>
    </thead>
    <tbody>
        @php $grandTotal = 0; @endphp
        @foreach($report['rows'] as $row)
            @php $grandTotal += $row['amount_cents']; @endphp
            <tr>
                <td>{{ $row['period'] }}</td>
                <td>{{ number_format($row['payment_count']) }}</td>
                <td>{{ number_format($row['amount_cents'] / 100, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('pdf.total_caps') }}</td>
            <td>{{ number_format($report['summary']['total_payments']) }}</td>
            <td>{{ number_format($grandTotal / 100, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">{{ __('pdf.collections_by_method') }}</div>
<table>
    <thead>
        <tr><th>{{ __('pdf.method_column') }}</th><th>{{ __('pdf.count') }}</th><th>{{ __('pdf.amount_tzs') }}</th></tr>
    </thead>
    <tbody>
        @foreach($report['by_method'] as $m)
            <tr>
                <td>{{ mb_strtoupper(\App\Enums\PaymentMethod::tryFrom((string) $m['method'])?->label() ?? (string) $m['method']) }}</td>
                <td>{{ number_format($m['count']) }}</td>
                <td>{{ number_format($m['amount_cents'] / 100, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">{{ __('pdf.collections_by_class') }}</div>
<table>
    <thead>
        <tr><th>{{ __('pdf.class') }}</th><th>{{ __('pdf.amount_tzs') }}</th></tr>
    </thead>
    <tbody>
        @foreach($report['by_class'] as $c)
            <tr>
                <td>{{ $c['class'] }}</td>
                <td>{{ number_format($c['amount_cents'] / 100, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">{{ __('pdf.report_footer') }}</div>
</body>
</html>
