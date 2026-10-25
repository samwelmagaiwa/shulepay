@php
    $m = fn ($c) => 'TZS '.number_format(((int) $c) / 100, 0, '.', ',');
    $net = (int) $report['net_change_cents'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.reports._style')
</style>
</head>
<body>
<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>{{ __('pdf.reports.cash_flow.title') }}</p>
    <p>{{ __('pdf.reports.cash_flow.period', ['from' => $report['period']['from'], 'to' => $report['period']['to']]) }}</p>
    <p>{{ __('pdf.generated') }}: {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</div>

<div style="max-width:560px; margin:0 auto;">
    <div class="section-title">{{ __('pdf.reports.cash_flow.operating') }}</div>
    <table class="grid">
        <tbody>
            <tr><td>{{ __('pdf.reports.cash_flow.fee_collections') }}</td><td class="r pos">{{ $m($report['operating']['fee_collections']) }}</td></tr>
            <tr><td>{{ __('pdf.reports.cash_flow.expense_payments') }}</td><td class="r neg">− {{ $m($report['operating']['expense_payments']) }}</td></tr>
            <tr><td>{{ __('pdf.reports.cash_flow.payroll_payments') }}</td><td class="r neg">− {{ $m($report['operating']['payroll_payments']) }}</td></tr>
            <tr><td>{{ __('pdf.reports.cash_flow.supplier_payments') }}</td><td class="r neg">− {{ $m($report['operating']['supplier_payments']) }}</td></tr>
            <tr class="subtotal"><td>{{ __('pdf.reports.cash_flow.net_operating') }}</td><td class="r">{{ $m($report['operating']['net']) }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">{{ __('pdf.reports.cash_flow.investing') }}</div>
    <table class="grid">
        <tbody>
            <tr><td>{{ __('pdf.reports.cash_flow.asset_purchases') }}</td><td class="r neg">− {{ $m($report['investing']['asset_purchases']) }}</td></tr>
            <tr class="subtotal"><td>{{ __('pdf.reports.cash_flow.net_investing') }}</td><td class="r">{{ $m($report['investing']['net']) }}</td></tr>
        </tbody>
    </table>

    <table class="grid" style="margin-top:14px;">
        <tbody>
            <tr class="total"><td>{{ $net >= 0 ? __('pdf.reports.cash_flow.net_increase') : __('pdf.reports.cash_flow.net_decrease') }}</td><td class="r">{{ $m(abs($net)) }}</td></tr>
        </tbody>
    </table>
</div>
<div class="footer">{{ __('pdf.report_footer') }}</div>
</body>
</html>
