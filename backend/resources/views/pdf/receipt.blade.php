{{--
  Receipt — A4.

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

    // The receipt must stay on a single page. A4 fits far more than the old A5
    // layout, so the thresholds are higher; past $maxLines the tail collapses into
    // one "other items" row and the footer can never spill onto a second page.
    $allLines = $invoice?->lines ?? collect();
    $dense = $allLines->count() > 6;
    $maxLines = 8;

    $shownLines = $allLines->take($maxLines);
    $hiddenLines = $allLines->slice($maxLines);
    $hiddenTotal = $hiddenLines->sum(fn ($l) => $l->amount_cents->cents());
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  /* Sized for A4 (210mm x 297mm) — the paper the school actually prints on.
     Previously A5; the same block at A5 sizing looks lost on a full sheet. */
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; padding: 26px 38px; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .hr     { border-top: 1px dashed #555; margin: 7px 0; }
  .hr-solid { border-top: 1.5px dashed #111; margin: 8px 0; }
  .logo-img { max-width: 90px; max-height: 60px; margin-bottom: 3px; }
  .app-name { font-size: 19px; font-weight: bold; color: #007f3e; letter-spacing: .5px; }
  .sub    { font-size: 10px; color: #555; }
  .doc-title { font-size: 11px; letter-spacing: 3px; color: #333; }
  .receipt-no { font-size: 17px; font-weight: bold; margin: 3px 0; }

  /* Two-column label/value row */
  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 2.5px 0; vertical-align: top; font-size: 12px; }
  table.kv td.k { color: #555; }
  table.kv td.v { text-align: right; font-weight: bold; }

  /* Particulars (invoice line items) */
  table.items { width: 100%; border-collapse: collapse; margin-top: 2px; }
  table.items th { font-size: 10px; letter-spacing: .5px; text-transform: uppercase; color: #555;
                   border-bottom: 1.5px solid #999; padding: 4px 0; text-align: left; }
  table.items th.amt, table.items td.amt { text-align: right; }
  table.items td { font-size: 12px; padding: 4px 0; border-bottom: 1.5px dashed #888; }
  /* Applied when there are many fee lines, to keep everything on one page */
  table.items.dense th { font-size: 10px; padding: 3px 0; }
  table.items.dense td { font-size: 11px; padding: 2px 0; }

  .amount-box { border: 2px solid #007f3e; border-radius: 5px; padding: 10px; margin: 11px 0; }
  .amount-lbl { font-size: 11px; letter-spacing: 2px; color: #555; text-align: center; }
  .amount { font-size: 26px; font-weight: bold; color: #007f3e; text-align: center; margin-top: 2px; }

  .balance-paid { color: #007f3e; font-weight: bold; }
  .balance-due  { color: #c0292b; font-weight: bold; }
  .settled { font-size: 13px; letter-spacing: 1.5px; }
  .footer { font-size: 10px; color: #777; text-align: center; margin-top: 11px; line-height: 1.4; }
</style>
</head>
<body>

  {{-- ── Letterhead (shared with statements and reports) ─────── --}}
  @include('pdf.partials.letterhead', [
    'lh' => $lh,
    'docTitle' => 'Risiti ya Malipo',
    'compact' => false,
  ])

  {{-- Receipt number and issue date, boxed so they read as the document's
       reference rather than part of the school's address block. --}}
  <table style="width:100%; border-collapse:collapse; margin-top:10px;">
    <tr>
      <td style="font-size:10px; color:#666; letter-spacing:1px;">NAMBA YA RISITI</td>
      <td style="font-size:10px; color:#666; text-align:right; letter-spacing:1px;">TAREHE</td>
    </tr>
    <tr>
      <td style="font-size:19px; font-weight:bold; color:#007f3e;">{{ $receipt->receipt_number }}</td>
      <td style="font-size:12px; text-align:right; font-weight:bold;">
        {{ $receipt->issued_at?->format('d/m/Y H:i') }}
      </td>
    </tr>
  </table>

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
  @if($allLines->isNotEmpty())
  <div class="hr"></div>
  <table class="items {{ $dense ? 'dense' : '' }}">
    <thead>
      <tr>
        <th>Maelezo</th>
        <th class="amt">Kiasi</th>
      </tr>
    </thead>
    <tbody>
      @foreach($shownLines as $line)
      <tr>
        <td>{{ $line->description }}</td>
        <td class="amt">{{ $money($line->amount_cents->cents()) }}</td>
      </tr>
      @endforeach
      @if($hiddenLines->isNotEmpty())
      <tr>
        <td>Vipengele vingine ({{ $hiddenLines->count() }})</td>
        <td class="amt">{{ $money($hiddenTotal) }}</td>
      </tr>
      @endif
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
    <div class="center bold settled" style="color:#007f3e; margin-top:10px;">
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
