{{--
  Bulk invoice printout — every invoice matching a status filter (Partial /
  Unpaid), one per page. Mirrors receipt.blade.php's styling; DomPDF has no
  flexbox, so every two-column row is a <table>.
--}}
@php
    $money = fn ($cents) => 'TZS '.number_format(((int) $cents) / 100, 0, '.', ',');
    $statusLabel = ['unpaid' => 'HAJALIPA', 'partial' => 'AMELIPA KIASI', 'paid' => 'AMELIPA'][$statusFilter] ?? strtoupper($statusFilter);
@endphp
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
  .page { padding: 26px 38px; }
  .page:not(:last-child) { page-break-after: always; }
  .hr { border-top: 1px dashed #555; margin: 8px 0; }
  .hr-solid { border-top: 1.5px dashed #111; margin: 8px 0; }

  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 3px 0; vertical-align: top; font-size: 12px; }
  table.kv td.k { color: #555; }
  table.kv td.v { text-align: right; font-weight: bold; }

  .balance-paid { color: #007f3e; font-weight: bold; }
  .balance-due  { color: #c0292b; font-weight: bold; }

  .status-badge { display: inline-block; padding: 3px 14px; border-radius: 4px;
                  font-size: 11px; font-weight: bold; letter-spacing: 1.5px; }
  .status-unpaid  { background: #f8d7da; color: #842029; }
  .status-partial { background: #fff3cd; color: #856404; }
  .status-paid    { background: #d1e7dd; color: #0f5132; }

  .footer { font-size: 10px; color: #777; text-align: center; margin-top: 16px; }
</style>
</head>
<body>

@forelse($rows as $row)
  <div class="page">
    @include('pdf.partials.letterhead', [
      'lh' => $row->lh,
      'docTitle' => 'Taarifa ya Ankara',
      'compact' => true,
    ])

    <div style="text-align:center; margin-top:8px;">
      <span class="status-badge status-{{ $row->status }}">{{ $statusLabel }}</span>
    </div>

    <div class="hr"></div>

    <table class="kv">
      <tr>
        <td class="k">Mwanafunzi</td>
        <td class="v">{{ $row->student?->fullName() ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">Namba ya Usajili</td>
        <td class="v">{{ $row->enrollment?->admission_number ?: '—' }}</td>
      </tr>
      @if($row->enrollment?->schoolClass)
      <tr>
        <td class="k">Darasa</td>
        <td class="v">{{ $row->enrollment->schoolClass->name }}</td>
      </tr>
      @endif
      @if($row->guardian)
      <tr>
        <td class="k">Mzazi / Mlezi</td>
        <td class="v">{{ $row->guardian->fullName() }}</td>
      </tr>
      @endif
    </table>

    <div class="hr"></div>

    <table class="kv">
      <tr>
        <td class="k">Ankara</td>
        <td class="v">{{ $row->invoice_number }}</td>
      </tr>
      <tr>
        <td class="k">Muhula</td>
        <td class="v">{{ $row->term ?: '—' }}</td>
      </tr>
      @if($row->academic_year)
      <tr>
        <td class="k">Mwaka wa Masomo</td>
        <td class="v">{{ $row->academic_year }}</td>
      </tr>
      @endif
    </table>

    <div class="hr-solid"></div>

    <table class="kv">
      <tr>
        <td class="k">Jumla ya Ankara</td>
        <td class="v">{{ $money($row->gross_cents) }}</td>
      </tr>
      <tr>
        <td class="k">Alicholipa</td>
        <td class="v balance-paid">{{ $money($row->paid_cents) }}</td>
      </tr>
    </table>
    <div class="hr-solid"></div>
    <table class="kv">
      <tr>
        <td class="k" style="font-weight:bold;">SALIO</td>
        <td class="v {{ $row->balance_cents > 0 ? 'balance-due' : 'balance-paid' }}">
          {{ $money($row->balance_cents) }}
        </td>
      </tr>
    </table>

    <div class="hr"></div>
    <div class="footer">
      {{ $row->lh['name'] }} &copy; {{ date('Y') }} — Imetolewa {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
  </div>
@empty
  <div class="page">
    <p style="text-align:center; color:#777; margin-top:60px;">Hakuna ankara zinazolingana na kigezo hiki.</p>
  </div>
@endforelse

</body>
</html>
