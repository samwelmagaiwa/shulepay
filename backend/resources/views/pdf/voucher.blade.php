{{--
  Payment voucher — A4. Same letterhead, amount box and typography as the fee receipt.
  DomPDF has no flexbox: every two-column row is a <table>.
--}}
@php
    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');
    $dt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—';
    $dtt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y H:i') : '';
    // spelled out, not built from the type, so the translation check can see every key this view uses
    $typeLabel = match ($v['type']) {
        'supplier_payment' => __('pdf.voucher.type_supplier_payment'),
        'expense' => __('pdf.voucher.type_expense'),
        default => __('pdf.voucher.type_payroll'),
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; padding: 26px 38px; }
  .bold { font-weight: bold; }
  .hr { border-top: 1px dashed #555; margin: 8px 0; }
  .hr-solid { border-top: 1.5px dashed #111; margin: 8px 0; }

  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 2.5px 0; vertical-align: top; font-size: 12px; }
  table.kv td.k { color: #555; width: 38%; }
  table.kv td.v { text-align: right; font-weight: bold; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 2px; }
  table.items th { font-size: 11px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #222; border-bottom: 1.5px solid #999; padding: 4px 0; text-align: left; }
  table.items th.amt, table.items td.amt { text-align: right; }
  table.items th.desc { text-align: center; }
  table.items td { font-size: 12px; padding: 4px 0; border-bottom: 1.5px dashed #888; }

  .amount-box { border: 2px solid #007f3e; border-radius: 5px; padding: 10px; margin: 11px 0 6px; }
  .amount-lbl { font-size: 11px; letter-spacing: 2px; color: #555; text-align: center; }
  .amount { font-size: 26px; font-weight: bold; color: #007f3e; text-align: center; margin-top: 2px; }
  .words { font-size: 11px; font-style: italic; color: #333; text-align: center; margin-bottom: 8px; }
  /* section headings: centred and bold, like the document title */
  .sec { font-size: 11px; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: #222; text-align: center; margin: 3px 0 5px; }
  .deduct { color: #c0292b; }

  table.sign { width: 100%; border-collapse: collapse; margin-top: 26px; }
  table.sign td { width: 33%; vertical-align: bottom; padding: 0 8px; text-align: center; font-size: 10px; color: #444; }
  table.sign .line { border-top: 1px solid #111; padding-top: 3px; margin-top: 34px; }
  table.sign .nm { font-weight: bold; color: #111; font-size: 11px; min-height: 13px; }
  .footer { font-size: 10px; color: #777; text-align: center; margin-top: 16px; line-height: 1.4; }
  .stamp { border: 1.5px solid #007f3e; color: #007f3e; font-weight: bold; letter-spacing: 2px; font-size: 11px; padding: 2px 10px; }
</style>
</head>
<body>

  @include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.voucher.title'), 'compact' => false])

  <table style="width:100%; border-collapse:collapse; margin-top:10px;">
    <tr>
      <td style="font-size:10px; color:#666; letter-spacing:1px;">{{ __('pdf.voucher.number') }}</td>
      <td style="font-size:10px; color:#666; text-align:right; letter-spacing:1px;">{{ __('pdf.voucher.date') }}</td>
    </tr>
    <tr>
      <td style="font-size:19px; font-weight:bold; color:#007f3e;">{{ $v['number'] }}</td>
      <td style="font-size:12px; text-align:right; font-weight:bold;">{{ $dt($v['date']) }}</td>
    </tr>
    <tr>
      <td style="padding-top:4px;"><span class="stamp">{{ strtoupper($typeLabel) }}</span></td>
      <td></td>
    </tr>
  </table>

  <div class="hr"></div>

  {{-- ── Payee ── --}}
  <div class="sec">{{ __('pdf.voucher.paid_to') }}</div>
  <table class="kv">
    <tr><td class="k">{{ __('pdf.voucher.payee') }}</td><td class="v">{{ $v['payee'] ?: '—' }}</td></tr>
    @foreach($v['payee_lines'] as [$label, $value])
      <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
    @endforeach
  </table>

  <div class="hr"></div>

  {{-- ── Payment details ── --}}
  <div class="sec">{{ __('pdf.voucher.payment_details') }}</div>
  <table class="kv">
    <tr><td class="k">{{ __('pdf.voucher.method') }}</td><td class="v">{{ $v['method'] ?: '—' }}</td></tr>
    @if($v['reference'])<tr><td class="k">{{ __('pdf.voucher.reference') }}</td><td class="v">{{ $v['reference'] }}</td></tr>@endif
    @if($v['efd'])<tr><td class="k">{{ __('pdf.voucher.efd') }}</td><td class="v">{{ $v['efd'] }}</td></tr>@endif
    <tr><td class="k">{{ __('pdf.voucher.being_payment_of') }}</td><td class="v" style="font-weight:normal;">{{ $v['purpose'] }}</td></tr>
  </table>

  {{-- ── Particulars ── --}}
  @if(count($v['items']))
  <div class="hr"></div>
  <table class="items">
    <thead><tr><th class="desc">{{ __('pdf.voucher.description') }}</th><th class="amt">{{ __('pdf.voucher.amount') }}</th></tr></thead>
    <tbody>
      @foreach($v['items'] as $item)
        <tr><td>{{ $item['description'] }}</td><td class="amt">{{ $money($item['cents']) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ── Gross, deductions, net ── --}}
  <table class="kv" style="margin-top:6px;">
    <tr><td class="k">{{ __('pdf.voucher.gross') }}</td><td class="v">{{ $money($v['gross_cents']) }}</td></tr>
    @foreach($v['deductions'] as [$label, $cents])
      <tr><td class="k">{{ $label }}</td><td class="v deduct">- {{ $money($cents) }}</td></tr>
    @endforeach
  </table>

  <div class="amount-box">
    <div class="amount-lbl">{{ __('pdf.voucher.net_paid') }}</div>
    <div class="amount">{{ $money($v['net_cents']) }}</div>
  </div>
  <div class="words">{{ __('pdf.voucher.in_words') }}: {{ $words }}</div>

  @if($v['notes'])
    <div class="hr"></div>
    <table class="kv"><tr><td class="k">{{ __('pdf.voucher.notes') }}</td><td class="v" style="font-weight:normal;">{{ $v['notes'] }}</td></tr></table>
  @endif

  {{-- ── Signatures ── --}}
  <table class="sign">
    <tr>
      <td>
        <div class="nm">{{ $v['prepared_by'] ?: ' ' }}</div>
        <div style="color:#777;">{{ $v['prepared_by'] ? $dtt($v['prepared_at']) : ' ' }}</div>
        <div class="line">{{ __('pdf.voucher.prepared_by') }}</div>
      </td>
      <td>
        <div class="nm">{{ $v['approved_by'] ?: ' ' }}</div>
        <div style="color:#777;">{{ $v['approved_by'] ? $dtt($v['approved_at']) : ' ' }}</div>
        <div class="line">{{ __('pdf.voucher.approved_by') }}</div>
      </td>
      <td>
        <div class="nm">&nbsp;</div>
        <div style="color:#777;">&nbsp;</div>
        <div class="line">{{ __('pdf.voucher.received_by') }}</div>
      </td>
    </tr>
  </table>

  <div class="hr" style="margin-top:18px;"></div>
  <div class="footer">
    {{ __('pdf.voucher.footer') }}<br>
    {{ $lh['name'] }} &copy; {{ date('Y') }} {{ $lh['tagline'] }}
  </div>
</body>
</html>
