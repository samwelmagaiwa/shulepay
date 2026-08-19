<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { font-size: 18px; color: #065f46; }
    .header p  { font-size: 11px; color: #555; }
    .statement { max-width: 560px; margin: 0 auto; }
    .section-title {
        font-size: 12px; font-weight: bold; color: #fff;
        background: #065f46; padding: 6px 10px; margin: 16px 0 0;
        border-radius: 3px 3px 0 0;
    }
    table { width: 100%; border-collapse: collapse; border: 1px solid #d1fae5; margin-bottom: 0; }
    tr td { padding: 5px 10px; border-bottom: 1px solid #d1fae5; font-size: 10px; }
    tr td:last-child { text-align: right; }
    tr.subtotal td { font-weight: bold; background: #ecfdf5; }
    tr.total td { font-weight: bold; font-size: 12px; background: #065f46; color: #fff; }
    tr.net-income td { font-size: 14px; font-weight: bold; }
    tr.net-income.positive td { background: #d1fae5; color: #065f46; }
    tr.net-income.negative td { background: #fee2e2; color: #991b1b; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>Income Statement (Profit &amp; Loss)</p>
    <p>Period: {{ $report['period']['from'] }} to {{ $report['period']['to'] }}</p>
    <p>Generated: {{ now()->format('d M Y H:i') }}</p>
</div>

<div class="statement">
    {{-- REVENUE --}}
    <div class="section-title">REVENUE</div>
    <table>
        <tr>
            <td>Fee Collections</td>
            <td>TZS {{ number_format($report['revenue']['fee_collections'] / 100, 2) }}</td>
        </tr>
        <tr class="subtotal">
            <td>Total Revenue</td>
            <td>TZS {{ number_format($report['revenue']['total'] / 100, 2) }}</td>
        </tr>
    </table>

    {{-- EXPENSES --}}
    <div class="section-title">EXPENSES</div>
    <table>
        @foreach($report['expenses']['by_category'] as $cat)
        <tr>
            <td>{{ $cat['category'] }}</td>
            <td>TZS {{ number_format($cat['amount_cents'] / 100, 2) }}</td>
        </tr>
        @endforeach
        <tr>
            <td>Payroll</td>
            <td>TZS {{ number_format($report['expenses']['payroll'] / 100, 2) }}</td>
        </tr>
        <tr class="subtotal">
            <td>Total Expenses</td>
            <td>TZS {{ number_format($report['expenses']['total'] / 100, 2) }}</td>
        </tr>
    </table>

    {{-- NET INCOME --}}
    <table style="margin-top: 16px;">
        @php $net = $report['net_income_cents']; @endphp
        <tr class="net-income {{ $net >= 0 ? 'positive' : 'negative' }}">
            <td>{{ $net >= 0 ? 'NET INCOME' : 'NET LOSS' }}</td>
            <td>TZS {{ number_format(abs($net) / 100, 2) }}</td>
        </tr>
    </table>
</div>

<div class="footer">ShulePay &mdash; Fee Management System</div>
</body>
</html>
