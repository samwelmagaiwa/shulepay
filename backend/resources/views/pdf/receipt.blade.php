{{--
  Receipt — 80mm thermal roll.

  NOTE: DomPDF does not implement flexbox. An earlier version of this view used
  `display:flex; justify-content:space-between` for its label/value rows, which
  silently rendered as stacked blocks. Every two-column row here is a <table>,
  which DomPDF does lay out correctly.
--}}
@php
    $payment    = $receipt->payment;
    $invoice    = $payment?->invoice;
    $enrollment = $receipt->student?->currentEnrollment;

    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');

    $invoiceTotal = $invoice ? $invoice->total_amount_cents->cents() : 0;
    $invoicePaid  = $invoice ? $invoice->paidCents() : 0;
    $invoiceDue   = $invoice ? $invoice->balanceDueCents() : 0;

    $guardian = $receipt->student?->guardians?->first();
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; padding: 10px; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .hr     { border-top: 1px dashed #555; margin: 5px 0; }
  .hr-solid { border-top: 1px solid #111; margin: 5px 0; }
  .logo-img { max-width: 70px; max-height: 50px; margin-bottom: 4px; }
  .app-name { font-size: 14px; font-weight: bold; color: #007f3e; }
  .sub    { font-size: 8px; color: #555; }
  .doc-title { font-size: 9px; letter-spacing: 1px; color: #333; }
  .receipt-no { font-size: 12px; font-weight: bold; margin: 3px 0; }

  /* Two-column label/value row */
  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 1.5px 0; vertical-align: top; font-size: 9px; }
  table.kv td.k { color: #555; }
  table.kv td.v { text-align: right; font-weight: bold; }

  /* Particulars (invoice line items) */
  table.items { width: 100%; border-collapse: collapse; margin-top: 2px; }
  table.items th { font-size: 8px; text-transform: uppercase; color: #555;
                   border-bottom: 1px solid #999; padding: 2px 0; text-align: left; }
  table.items th.amt, table.items td.amt { text-align: right; }
  table.items td { font-size: 9px; padding: 2px 0; border-bottom: 1px dotted #ccc; }

  .amount-box { border: 1.5px solid #007f3e; padding: 5px; margin: 6px 0; }
  .amount-lbl { font-size: 8px; color: #555; text-align: center; }
  .amount { font-size: 16px; font-weight: bold; color: #007f3e; text-align: center; }

  .balance-paid { color: #007f3e; font-weight: bold; }
  .balance-due  { color: #c0292b; font-weight: bold; }
  .footer { font-size: 7.5px; color: #777; text-align: center; margin-top: 6px; line-height: 1.4; }
</style>
</head>
<body>

  {{-- ── Header ─────────────────────────────────────────────── --}}
  <div class="center">
    @if($logoBase64)
      <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo"><br>
    @endif
    <div class="app-name">{{ $appName }}</div>
    <div class="sub">{{ $appTagline }}</div>
    @if($enrollment?->school && $enrollment->school->name !== $appName)
      <div class="sub bold">{{ $enrollment->school->name }}</div>
    @endif
  </div>

  <div class="hr"></div>

  <div class="center">
    <div class="doc-title">RISITI YA MALIPO</div>
    <div class="receipt-no">{{ $receipt->receipt_number }}</div>
    <div class="sub">{{ $receipt->issued_at?->format('d/m/Y H:i') }}</div>
  </div>

  <div class="hr"></div>

  {{-- ── Student ────────────────────────────────────────────── --}}
  <table class="kv">
    <tr>
      <td class="k">Mwanafunzi</td>
      <td class="v">{{ $receipt->student?->fullName() ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Namba ya Usajili</td>
      {{-- admission_number lives on the enrollment, not on the student --}}
      <td class="v">{{ $enrollment?->admission_number ?: '—' }}</td>
    </tr>
    @if($enrollment?->schoolClass)
    <tr>
      <td class="k">Darasa</td>
      <td class="v">{{ $enrollment->schoolClass->name }}</td>
    </tr>
    @endif
    @if($guardian)
    <tr>
      <td class="k">Mzazi / Mlezi</td>
      <td class="v">{{ $guardian->fullName() }}</td>
    </tr>
    @endif
  </table>

  @if($payment)
  <div class="hr"></div>

  {{-- ── Payment / invoice context ──────────────────────────── --}}
  <table class="kv">
    <tr>
      <td class="k">Ankara</td>
      <td class="v">{{ $invoice?->invoice_number ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Muhula</td>
      <td class="v">{{ $invoice?->term?->name ?: '—' }}</td>
    </tr>
    @if($invoice?->academicYear)
    <tr>
      <td class="k">Mwaka wa Masomo</td>
      <td class="v">{{ $invoice->academicYear->name }}</td>
    </tr>
    @endif
    <tr>
      <td class="k">Tarehe ya Malipo</td>
      <td class="v">{{ $payment->paid_at?->format('d/m/Y') ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Njia ya Malipo</td>
      <td class="v">{{ $payment->method->label() }}</td>
    </tr>
    @if($payment->reference_number)
    <tr>
      <td class="k">Kumbukumbu</td>
      <td class="v">{{ $payment->reference_number }}</td>
    </tr>
    @endif
  </table>

  {{-- ── Particulars: what the invoice is made of ───────────── --}}
  @if($invoice && $invoice->lines->isNotEmpty())
  <div class="hr"></div>
  <table class="items">
    <thead>
      <tr>
        <th>Maelezo</th>
        <th class="amt">Kiasi</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->lines as $line)
      <tr>
        <td>{{ $line->description }}</td>
        <td class="amt">{{ $money($line->amount_cents->cents()) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- ── Amount received ────────────────────────────────────── --}}
  <div class="amount-box">
    <div class="amount-lbl">KIASI KILICHOLIPWA</div>
    <div class="amount">{{ $money($payment->amount_cents->cents()) }}</div>
  </div>

  {{-- ── Running invoice position ───────────────────────────── --}}
  <table class="kv">
    <tr>
      <td class="k">Jumla ya Ankara</td>
      <td class="v">{{ $money($invoiceTotal) }}</td>
    </tr>
    <tr>
      <td class="k">Jumla Iliyolipwa</td>
      <td class="v balance-paid">{{ $money($invoicePaid) }}</td>
    </tr>
  </table>
  <div class="hr-solid"></div>
  <table class="kv">
    <tr>
      <td class="k bold">SALIO</td>
      <td class="v {{ $invoiceDue > 0 ? 'balance-due' : 'balance-paid' }}">
        {{ $money($invoiceDue) }}
      </td>
    </tr>
  </table>

  @if($invoiceDue <= 0)
    <div class="center sub bold" style="color:#007f3e; margin-top:4px;">
      ✓ ANKARA IMELIPWA YOTE
    </div>
  @endif

  @if($payment->recorder)
  <div class="hr"></div>
  <table class="kv">
    <tr>
      <td class="k">Imepokelewa na</td>
      <td class="v">{{ $payment->recorder->name }}</td>
    </tr>
  </table>
  @endif
  @endif

  <div class="hr"></div>
  <div class="footer">
    Asante kwa malipo yako. Hati hii ni ushahidi wa malipo.<br>
    {{ $appName }} &copy; {{ date('Y') }} {{ $appTagline }}
  </div>
</body>
</html>
