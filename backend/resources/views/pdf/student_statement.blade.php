{{--
  Consolidated student statement/receipt — every invoice for one student on
  a single printout: term, billed, paid, balance per invoice, the payments
  behind each, and grand totals. Mirrors pdf/receipt.blade.php's styling
  (DomPDF has no flexbox — every two-column row is a <table>).
--}}
@php
    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');
    $guardian = $student->guardians?->first();

    // This must stay a single page however many invoices a student has accumulated
    // (four per academic year, so a pupil here for six years reaches 24). Density
    // steps up before anything is hidden, so "all invoices" stays true:
    //   <= 6   full detail, one line per payment
    //   7-14   condensed rows, payments summarised to a count
    //   > 14   condensed, and the oldest collapse into a single carried-forward row
    $count = $invoices->count();
    $dense = $count > 6;
    $showPayments = $count <= 6;
    $maxRows = 14;

    $shownInvoices = $count > $maxRows ? $invoices->slice(-$maxRows) : $invoices;
    $olderInvoices = $count > $maxRows ? $invoices->slice(0, $count - $maxRows) : collect();
    $olderGross = $olderInvoices->sum(fn ($i) => $i->gross_cents);
    $olderPaid = $olderInvoices->sum(fn ($i) => $i->paid_cents);
    $olderBalance = $olderInvoices->sum(fn ($i) => $i->balance_cents);
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; padding: 16px 24px; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .hr     { border-top: 1px dashed #555; margin: 6px 0; }
  .hr-solid { border-top: 1.5px solid #111; margin: 5px 0; }
  .logo-img { max-width: 90px; max-height: 60px; margin-bottom: 3px; }
  .app-name { font-size: 19px; font-weight: bold; color: #007f3e; letter-spacing: .5px; }
  .sub    { font-size: 10px; color: #555; }
  .doc-title { font-size: 11px; letter-spacing: 3px; color: #333; }

  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 2px 0; vertical-align: top; font-size: 10.5px; }
  table.kv td.k { color: #555; }
  table.kv td.v { text-align: right; font-weight: bold; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
  table.items th { font-size: 8.5px; letter-spacing: .4px; text-transform: uppercase; color: #555;
                   border-bottom: 1.5px solid #999; padding: 3px 2px; text-align: left; }
  table.items th.amt, table.items td.amt { text-align: right; }
  table.items td { font-size: 9.5px; padding: 3px 2px; border-bottom: 1px dotted #ccc; vertical-align: top; }

  .status-paid    { color: #007f3e; font-weight: bold; }
  .status-partial { color: #b45309; font-weight: bold; }
  .status-unpaid  { color: #c0292b; font-weight: bold; }

  .payment-line { font-size: 8.5px; color: #666; }

  /* Applied once a student has enough invoices that full-detail rows would
     push the totals onto a second page. */
  table.items.dense th { font-size: 7.5px; padding: 2px 2px; }
  table.items.dense td { font-size: 8.5px; padding: 1.5px 2px; }
  table.items.dense .status-paid,
  table.items.dense .status-partial,
  table.items.dense .status-unpaid { display: inline; font-size: 8px; }

  .amount-box { border: 2px solid #007f3e; border-radius: 4px; padding: 7px; margin: 10px 0 6px; }
  .amount-lbl { font-size: 9px; letter-spacing: 1.5px; color: #555; text-align: center; }
  .amount { font-size: 20px; font-weight: bold; text-align: center; margin-top: 1px; }
  .balance-paid { color: #007f3e; }
  .balance-due  { color: #c0292b; }

  .footer { font-size: 9px; color: #777; text-align: center; margin-top: 10px; line-height: 1.4; }
</style>
</head>
<body>

  {{-- Shared letterhead — logo, contacts and postal address all come from the
       school's branding settings. See pdf/partials/letterhead.blade.php. --}}
  @include('pdf.partials.letterhead', [
    'lh' => $lh,
    'docTitle' => 'Taarifa ya Malipo Yote (Ankara Zote)',
    'compact' => true,
  ])

  <div class="center" style="margin-top:6px;">
    <div class="sub">{{ now()->format('d/m/Y H:i') }}</div>
  </div>

  <div class="hr"></div>

  <table class="kv">
    <tr>
      <td class="k">Mwanafunzi</td>
      <td class="v">{{ $student->fullName() ?: '—' }}</td>
    </tr>
    <tr>
      <td class="k">Namba ya Usajili</td>
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

  <div class="hr"></div>

  <table class="items {{ $dense ? 'dense' : '' }}">
    <thead>
      <tr>
        <th>Muhula</th>
        <th class="amt">Ankara</th>
        <th class="amt">Alicholipa</th>
        <th class="amt">Salio</th>
      </tr>
    </thead>
    <tbody>
      @if($olderInvoices->isNotEmpty())
      <tr>
        <td><em>Ankara za awali ({{ $olderInvoices->count() }})</em></td>
        <td class="amt">{{ $money($olderGross) }}</td>
        <td class="amt">{{ $money($olderPaid) }}</td>
        <td class="amt {{ $olderBalance > 0 ? 'balance-due' : 'balance-paid' }}">{{ $money($olderBalance) }}</td>
      </tr>
      @endif
      @forelse($shownInvoices as $inv)
      <tr>
        <td>
          {{ $inv->term ?: '—' }}
          <span class="status-{{ $inv->status }}"{!! $dense ? '' : ' style="display:block"' !!}>
            {{ ['paid' => 'Amelipa', 'partial' => 'Amelipa Kiasi', 'unpaid' => 'Hajalipa'][$inv->status] ?? $inv->status }}
          </span>
          @if($showPayments)
            @foreach($inv->payments as $p)
              <div class="payment-line">
                {{ $p->paid_at?->format('d/m/Y') }} — {{ $money($p->amount_cents) }}
                @if($p->reference_number) ({{ $p->reference_number }}) @endif
              </div>
            @endforeach
          @elseif($inv->payments->count())
            <span class="payment-line">({{ $inv->payments->count() }} malipo)</span>
          @endif
        </td>
        <td class="amt">{{ $money($inv->gross_cents) }}</td>
        <td class="amt">{{ $money($inv->paid_cents) }}</td>
        <td class="amt {{ $inv->balance_cents > 0 ? 'balance-due' : 'balance-paid' }}">{{ $money($inv->balance_cents) }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="center">Hakuna ankara.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="hr-solid"></div>

  <table class="kv">
    <tr>
      <td class="k">Jumla ya Ankara Zote</td>
      <td class="v">{{ $money($totalInvoiced) }}</td>
    </tr>
    <tr>
      <td class="k">Jumla Iliyolipwa</td>
      <td class="v balance-paid">{{ $money($totalPaid) }}</td>
    </tr>
  </table>

  <div class="amount-box">
    <div class="amount-lbl">SALIO LA JUMLA (MADENI YOTE)</div>
    <div class="amount {{ $totalBalance > 0 ? 'balance-due' : 'balance-paid' }}">{{ $money($totalBalance) }}</div>
  </div>

  @if($totalBalance <= 0)
    <div class="center bold" style="color:#007f3e;">✓ ANKARA ZOTE ZIMELIPWA</div>
  @endif

  <div class="hr"></div>
  <div class="footer">
    Taarifa hii inaonyesha ankara zote za mwanafunzi huyu na malipo yaliyofanyika.<br>
    {{ $appName }} &copy; {{ date('Y') }} {{ $appTagline }}
  </div>
</body>
</html>
