{{--
  Student fee statement (A4).

  NOTE: DomPDF does not implement flexbox — every layout row here is a table.
--}}
@php
    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');
    $billedOf = fn ($i) => $i->total_amount_cents->cents() + $i->arrears_cents->cents() - $i->discount_cents->cents();
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; padding: 30px 34px; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .muted  { color: #555; }

  .logo-img   { max-width: 90px; max-height: 62px; margin-bottom: 4px; }
  .app-name   { font-size: 20px; font-weight: bold; color: #007f3e; }
  .sub        { font-size: 10px; color: #555; }
  .doc-title  { font-size: 13px; font-weight: bold; letter-spacing: 2px; margin-top: 10px; }
  .hr         { border-top: 1.5px solid #007f3e; margin: 10px 0; }

  table.kv { width: 100%; border-collapse: collapse; margin-top: 4px; }
  table.kv td { padding: 2px 0; font-size: 11px; vertical-align: top; }
  table.kv td.k { color: #555; width: 22%; }
  table.kv td.v { font-weight: bold; }

  table.inv { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.inv th {
    font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #fff;
    background: #007f3e; padding: 6px 5px; text-align: left;
  }
  table.inv td { font-size: 10.5px; padding: 5px; border-bottom: 1px solid #e6e6e6; }
  table.inv tr:nth-child(even) td { background: #fafafa; }
  table.inv .amt { text-align: right; }

  .pay-line { font-size: 9.5px; color: #555; padding-left: 8px; }

  table.totals { width: 46%; border-collapse: collapse; margin-top: 12px; margin-left: 54%; }
  table.totals td { padding: 4px 6px; font-size: 11px; }
  table.totals td.k { color: #555; }
  table.totals td.v { text-align: right; font-weight: bold; }
  table.totals tr.grand td { border-top: 2px solid #111; font-size: 13px; padding-top: 6px; }

  .paid { color: #007f3e; }
  .due  { color: #c0292b; }
  .badge { font-size: 9px; padding: 1px 5px; border-radius: 3px; font-weight: bold; }
  .b-paid    { background: rgba(0,127,62,.12);  color: #007f3e; }
  .b-partial { background: rgba(253,126,20,.14); color: #b45309; }
  .b-unpaid  { background: rgba(192,41,43,.12);  color: #c0292b; }

  .footer { font-size: 9px; color: #777; text-align: center; margin-top: 22px; line-height: 1.5; }
</style>
</head>
<body>

  @include('pdf.partials.letterhead', [
    'lh' => $lh,
    'docTitle' => 'Taarifa ya Ada',
    'compact' => false,
  ])

  <div class="center" style="margin-top:6px;">
    <div class="sub">Imetolewa: {{ now()->format('d/m/Y H:i') }}</div>
  </div>

  <div class="hr"></div>

  <table class="kv">
    <tr>
      <td class="k">Mwanafunzi</td>
      <td class="v">{{ $student->fullName() }}</td>
      <td class="k">Namba ya Usajili</td>
      <td class="v">{{ $enrollment?->admission_number ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Darasa</td>
      <td class="v">{{ $enrollment?->schoolClass?->name ?: '—' }}</td>
      <td class="k">Shule</td>
      <td class="v">{{ $school?->name ?: '—' }}</td>
    </tr>
  </table>

  @if($invoices->isEmpty())
    <p class="muted center" style="margin-top:30px;">Hakuna ankara kwa mwanafunzi huyu.</p>
  @else
    <table class="inv">
      <thead>
        <tr>
          <th>Ankara</th>
          <th>Muhula</th>
          <th>Mwaka</th>
          <th class="amt">Jumla</th>
          <th class="amt">Imelipwa</th>
          <th class="amt">Salio</th>
          <th>Hali</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoices as $inv)
          @php
            $billed = $billedOf($inv);
            $paid   = $inv->paidCents();
            $bal    = max(0, $billed - $paid);
            $status = $inv->status?->value ?? 'unpaid';
          @endphp
          <tr>
            <td>{{ $inv->invoice_number }}</td>
            <td>{{ $inv->term?->name ?: '—' }}</td>
            <td>{{ $inv->academicYear?->name ?: '—' }}</td>
            <td class="amt">{{ $money($billed) }}</td>
            <td class="amt paid">{{ $money($paid) }}</td>
            <td class="amt {{ $bal > 0 ? 'due' : 'paid' }}">{{ $money($bal) }}</td>
            <td>
              <span class="badge b-{{ $status }}">
                {{ ['paid' => 'IMELIPWA', 'partial' => 'SEHEMU', 'unpaid' => 'HAIJALIPWA'][$status] ?? strtoupper($status) }}
              </span>
            </td>
          </tr>
          {{-- Each payment against this invoice, so the parent can reconcile --}}
          @foreach($inv->payments as $p)
            <tr>
              <td colspan="7" class="pay-line">
                ↳ {{ $p->paid_at?->format('d/m/Y') }} — {{ $p->method?->label() }}
                {{ $p->reference_number ? '('.$p->reference_number.')' : '' }}
                : {{ $money($p->amount_cents->cents()) }}
              </td>
            </tr>
          @endforeach
        @endforeach
      </tbody>
    </table>

    <table class="totals">
      <tr>
        <td class="k">Jumla ya Ada</td>
        <td class="v">{{ $money($totalBilled) }}</td>
      </tr>
      <tr>
        <td class="k">Jumla Iliyolipwa</td>
        <td class="v paid">{{ $money($totalPaid) }}</td>
      </tr>
      <tr class="grand">
        <td class="k bold">SALIO</td>
        <td class="v {{ $totalBalance > 0 ? 'due' : 'paid' }}">{{ $money($totalBalance) }}</td>
      </tr>
    </table>

    @if($totalBalance <= 0)
      <div class="center bold" style="color:#007f3e; margin-top:14px;">✓ ADA YOTE IMELIPWA</div>
    @endif
  @endif

  <div class="footer">
    Hati hii imetolewa na mfumo na ni sahihi bila saini.<br>
    {{ $appName }} &copy; {{ date('Y') }} {{ $appTagline }}
  </div>
</body>
</html>
