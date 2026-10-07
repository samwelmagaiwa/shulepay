{{--
  One student's consolidated statement — the actual content between <body>
  tags, factored out of pdf/student_statement.blade.php so the bulk-print
  job (pdf/bulk_invoices.blade.php) can render the exact same design per
  student instead of a different, plainer layout. Both call this with the
  same variables: $lh, $statementNumber, $student, $enrollment, $invoices,
  $totalInvoiced, $totalPaid, $totalBalance, $appName, $appTagline.
--}}
@php
    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');
    $guardian = $student->guardians?->first();

    // This must stay a single page however many invoices a student has accumulated
    // (four per academic year, so a pupil here for six years reaches 24). Rows
    // condense past 6 invoices, and past 14 the oldest collapse into a single
    // carried-forward row — payment dates/amounts are no longer printed per
    // invoice at all, only the paid/partial/unpaid indicator.
    $count = $invoices->count();
    $dense = $count > 6;
    $maxRows = 14;

    $shownInvoices = $count > $maxRows ? $invoices->slice(-$maxRows) : $invoices;
    $olderInvoices = $count > $maxRows ? $invoices->slice(0, $count - $maxRows) : collect();
    $olderGross = $olderInvoices->sum(fn ($i) => $i->gross_cents);
    $olderPaid = $olderInvoices->sum(fn ($i) => $i->paid_cents);
    $olderBalance = $olderInvoices->sum(fn ($i) => $i->balance_cents);
@endphp

{{-- Shared letterhead — logo, contacts and postal address all come from the
     school's branding settings. See pdf/partials/letterhead.blade.php. --}}
@include('pdf.partials.letterhead', [
  'lh' => $lh,
  'docTitle' => __('pdf.full_statement_title'),
  'compact' => true,
])

{{-- Statement reference and issue date, boxed the same way a single-payment
     receipt boxes its receipt number — this document covers many invoices/
     receipts at once, so it carries its own reference instead of any one
     receipt's number. --}}
<table style="width:100%; border-collapse:collapse; margin-top:10px;">
  <tr>
    <td style="font-size:10px; color:#666; letter-spacing:1px;">{{ __('pdf.statement_no') }}</td>
    <td style="font-size:10px; color:#666; text-align:right; letter-spacing:1px;">{{ __('pdf.date') }}</td>
  </tr>
  <tr>
    <td style="font-size:16px; font-weight:bold; color:#007f3e;">{{ $statementNumber }}</td>
    <td style="font-size:13px; text-align:right; font-weight:bold;">{{ now()->format('d/m/Y H:i') }}</td>
  </tr>
</table>

<div class="hr"></div>

<table class="kv">
  <tr>
    <td class="k">{{ __('pdf.student') }}</td>
    <td class="v">{{ $student->fullName() ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.admission_no') }}</td>
    <td class="v">{{ $enrollment?->admission_number ?: '—' }}</td>
  </tr>
  @if($enrollment?->schoolClass)
  <tr>
    <td class="k">{{ __('pdf.class') }}</td>
    <td class="v">{{ $enrollment->schoolClass->name }}</td>
  </tr>
  @endif
  @if($enrollment?->academicYear)
  <tr>
    <td class="k">{{ __('pdf.academic_year') }}</td>
    <td class="v">{{ $enrollment->academicYear->name }}</td>
  </tr>
  @endif
  @if($guardian)
  <tr>
    <td class="k">{{ __('pdf.guardian') }}</td>
    <td class="v">{{ $guardian->fullName() }}</td>
  </tr>
  @endif
</table>

<div class="hr"></div>

<table class="items {{ $dense ? 'dense' : '' }}">
  <thead>
    <tr>
      <th>{{ __('pdf.term') }}</th>
      <th class="amt">{{ __('pdf.invoice') }}</th>
      <th class="amt">{{ __('pdf.paid_column') }}</th>
      <th class="amt">{{ __('pdf.balance') }}</th>
    </tr>
  </thead>
  <tbody>
    @if($olderInvoices->isNotEmpty())
    <tr>
      <td><em>{{ __('pdf.earlier_invoices', ['count' => $olderInvoices->count()]) }}</em></td>
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
          {{ in_array($inv->status, ['paid', 'partial', 'unpaid'], true) ? __('pdf.status_'.$inv->status) : $inv->status }}
        </span>
        <span class="row-meta">
          {{ __('pdf.invoice_colon') }} {{ $inv->invoice_number ?: '—' }}
          @if($inv->method) &middot; {{ $inv->method->label() }} @endif
        </span>
      </td>
      <td class="amt">{{ $money($inv->gross_cents) }}</td>
      <td class="amt">{{ $money($inv->paid_cents) }}</td>
      <td class="amt {{ $inv->balance_cents > 0 ? 'balance-due' : 'balance-paid' }}">{{ $money($inv->balance_cents) }}</td>
    </tr>
    @empty
    <tr><td colspan="4" class="center">{{ __('pdf.no_invoices') }}</td></tr>
    @endforelse
  </tbody>
</table>

<div class="hr-solid"></div>

<table class="kv">
  <tr>
    <td class="k">{{ __('pdf.all_invoices_total') }}</td>
    <td class="v">{{ $money($totalInvoiced) }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.total_paid') }}</td>
    <td class="v balance-paid">{{ $money($totalPaid) }}</td>
  </tr>
</table>

<div class="amount-box">
  <div class="amount-lbl">{{ __('pdf.grand_balance') }}</div>
  <div class="amount {{ $totalBalance > 0 ? 'balance-due' : 'balance-paid' }}">{{ $money($totalBalance) }}</div>
</div>

@if($totalBalance <= 0)
  <div class="center bold" style="color:#007f3e;">{{ __('pdf.all_paid') }}</div>
@endif

<div class="hr"></div>
<div class="footer">
  {{ __('pdf.full_statement_footer') }}<br>
  {{ $appName }} &copy; {{ date('Y') }} {{ $appTagline }}
</div>
