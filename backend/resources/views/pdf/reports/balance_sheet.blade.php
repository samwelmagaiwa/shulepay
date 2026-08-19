<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .header { text-align: center; border-bottom: 2px solid #7c3aed; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { font-size: 18px; color: #4c1d95; }
    .header p  { font-size: 11px; color: #555; }
    .columns { width: 100%; }
    .col-left, .col-right { width: 48%; display: inline-block; vertical-align: top; }
    .col-left { margin-right: 4%; }
    .section-title {
        font-size: 12px; font-weight: bold; color: #fff;
        padding: 5px 10px; margin-bottom: 0; border-radius: 3px 3px 0 0;
    }
    .assets-title  { background: #4c1d95; }
    .liab-title    { background: #b45309; }
    .equity-title  { background: #065f46; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    td { padding: 5px 8px; border-bottom: 1px solid #ede9fe; font-size: 10px; }
    td:last-child { text-align: right; }
    tr.sub td { font-weight: bold; background: #ede9fe; }
    tr.total-row td { font-weight: bold; font-size: 12px; background: #4c1d95; color: #fff; }
    tr.liab-total td { background: #b45309; color: #fff; font-weight: bold; font-size: 12px; }
    tr.equity-total td { background: #065f46; color: #fff; font-weight: bold; font-size: 12px; }
    .description { font-size: 9px; color: #6b7280; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    .balance-check { text-align: center; margin-top: 12px; font-weight: bold; font-size: 11px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>Balance Sheet &mdash; As of {{ $report['as_of'] }}</p>
    <p>Generated: {{ now()->format('d M Y H:i') }}</p>
</div>

@php
    $assets      = $report['assets'];
    $liabilities = $report['liabilities'];
    $equity      = $report['equity'];
@endphp

{{-- Two-column layout via table (dompdf-friendly) --}}
<table style="border: none;">
<tr style="vertical-align: top;">

{{-- LEFT: ASSETS --}}
<td style="width:48%; padding: 0; border: none;">
    <div class="section-title assets-title">ASSETS</div>
    <table>
        <tr>
            <td>
                Cash &amp; Bank<br>
                <span class="description">{{ $assets['cash_and_bank']['description'] }}</span>
            </td>
            <td>TZS {{ number_format($assets['cash_and_bank']['amount_cents'] / 100, 2) }}</td>
        </tr>
        <tr>
            <td>
                Receivables<br>
                <span class="description">{{ $assets['receivables']['description'] }}</span>
            </td>
            <td>TZS {{ number_format($assets['receivables']['amount_cents'] / 100, 2) }}</td>
        </tr>
        <tr>
            <td>
                Fixed Assets<br>
                <span class="description">{{ $assets['fixed_assets']['description'] }}</span>
            </td>
            <td>TZS {{ number_format($assets['fixed_assets']['amount_cents'] / 100, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>TOTAL ASSETS</td>
            <td>TZS {{ number_format($assets['total'] / 100, 2) }}</td>
        </tr>
    </table>
</td>

<td style="width: 4%; border: none;"></td>

{{-- RIGHT: LIABILITIES + EQUITY --}}
<td style="width:48%; padding: 0; border: none;">
    <div class="section-title liab-title">LIABILITIES</div>
    <table>
        <tr>
            <td>
                Payables<br>
                <span class="description">{{ $liabilities['payables']['description'] }}</span>
            </td>
            <td>TZS {{ number_format($liabilities['payables']['amount_cents'] / 100, 2) }}</td>
        </tr>
        <tr class="liab-total">
            <td>TOTAL LIABILITIES</td>
            <td>TZS {{ number_format($liabilities['total'] / 100, 2) }}</td>
        </tr>
    </table>

    <div class="section-title equity-title" style="margin-top: 12px;">EQUITY</div>
    <table>
        <tr>
            <td>Retained Earnings / Fund Balance</td>
            <td>TZS {{ number_format($equity['retained'] / 100, 2) }}</td>
        </tr>
        <tr class="equity-total">
            <td>TOTAL EQUITY</td>
            <td>TZS {{ number_format($equity['total'] / 100, 2) }}</td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <tr style="background: #f3f4f6;">
            <td style="font-weight:bold;">LIABILITIES + EQUITY</td>
            <td style="font-weight:bold; text-align:right;">
                TZS {{ number_format(($liabilities['total'] + $equity['total']) / 100, 2) }}
            </td>
        </tr>
    </table>
</td>

</tr>
</table>

<div class="footer">ShulePay &mdash; Fee Management System</div>
</body>
</html>
