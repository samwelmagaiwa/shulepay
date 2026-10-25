@php
    $money = fn ($cents) => number_format(((int) $cents) / 100, 0, '.', ',');
    $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
    $period = ($from || $to)
        ? trim(($fmtDate($from) ?? '…').' – '.($fmtDate($to) ?? '…'))
        : __('pdf.supplier_statement.all_time');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; padding: 40px 32px 24px; }
  table { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 3px 4px; vertical-align: top; }
  table.kv td.k { color: #555; width: 16%; }
  table.kv td.v { font-weight: bold; width: 34%; }
  table.grid th { font-size: 10px; letter-spacing: .3px; text-transform: uppercase; color: #444; background: #f1f4f2;
                  border: 1px solid #999; padding: 5px 4px; text-align: left; }
  table.grid th.amt, table.grid td.amt { text-align: right; }
  table.grid td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; }
  .totals td { padding: 3px 6px; }
  .closing { border: 2px solid #007f3e; border-radius: 4px; padding: 10px; margin-top: 12px; text-align: center; }
  .closing .lbl { font-size: 10px; letter-spacing: 1.2px; color: #555; }
  .closing .amt { font-size: 22px; font-weight: bold; margin-top: 2px; }
  .owed { color: #c0292b; } .clear { color: #007f3e; }
  .footer { margin-top: 18px; font-size: 9px; color: #777; text-align: center; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.supplier_statement.title'), 'compact' => true])

<table class="kv" style="margin-top:8px;">
  <tr>
    <td class="k">{{ __('pdf.supplier_statement.supplier') }}</td><td class="v">{{ $supplier->name }}</td>
    <td class="k">{{ __('pdf.supplier_statement.period') }}</td><td class="v">{{ $period }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.supplier_statement.code') }}</td><td class="v">{{ $supplier->code ?: '—' }}</td>
    <td class="k">{{ __('pdf.supplier_statement.tin') }}</td><td class="v">{{ $supplier->tin ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.supplier_statement.phone') }}</td><td class="v">{{ $supplier->phone ?: '—' }}</td>
    <td colspan="2"></td>
  </tr>
</table>

<table class="grid" style="margin-top:10px;">
  <thead>
    <tr>
      <th>{{ __('pdf.supplier_statement.date') }}</th>
      <th>{{ __('pdf.supplier_statement.type') }}</th>
      <th>{{ __('pdf.supplier_statement.reference') }}</th>
      <th class="amt">{{ __('pdf.supplier_statement.billed') }}</th>
      <th class="amt">{{ __('pdf.supplier_statement.paid') }}</th>
      <th class="amt">{{ __('pdf.supplier_statement.balance') }}</th>
    </tr>
  </thead>
  <tbody>
    <tr style="background:#f7faf8;">
      <td colspan="5"><em>{{ __('pdf.supplier_statement.opening') }}</em></td>
      <td class="amt">{{ $money($statement['opening_balance_cents']) }}</td>
    </tr>
    @forelse($statement['lines'] as $line)
      <tr>
        <td>{{ $fmtDate($line['date']) }}</td>
        <td>{{ $line['type'] === 'bill' ? __('pdf.supplier_statement.bill') : __('pdf.supplier_statement.payment') }}</td>
        <td>{{ $line['ref'] ?: '—' }}@if(! empty($line['description']) && $line['type'] === 'payment') · {{ $line['description'] }}@endif</td>
        <td class="amt">{{ $line['debit_cents'] ? $money($line['debit_cents']) : '' }}</td>
        <td class="amt">{{ $line['credit_cents'] ? $money($line['credit_cents']) : '' }}</td>
        <td class="amt">{{ $money($line['balance_cents']) }}</td>
      </tr>
    @empty
      <tr><td colspan="6" style="text-align:center; color:#777;">{{ __('pdf.supplier_statement.no_lines') }}</td></tr>
    @endforelse
  </tbody>
</table>

<table class="totals" style="margin-top:8px; width:55%; margin-left:auto;">
  <tr><td>{{ __('pdf.supplier_statement.total_billed') }}</td><td class="amt" style="text-align:right;"><strong>{{ $money($statement['total_billed_cents']) }}</strong></td></tr>
  <tr><td>{{ __('pdf.supplier_statement.total_paid') }}</td><td class="amt" style="text-align:right;"><strong>{{ $money($statement['total_paid_cents']) }}</strong></td></tr>
</table>

<div class="closing">
  <div class="lbl">{{ strtoupper(__('pdf.supplier_statement.closing')) }}</div>
  <div class="amt {{ $statement['closing_balance_cents'] > 0 ? 'owed' : 'clear' }}">TZS {{ $money($statement['closing_balance_cents']) }}</div>
</div>

<div class="footer">{{ __('pdf.supplier_statement.footer') }} · {{ now()->format('d/m/Y H:i') }}</div>

</body>
</html>
