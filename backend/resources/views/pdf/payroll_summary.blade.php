@php
    $lh = \App\Support\SchoolLetterhead::for($school);
    $money = fn ($cents) => number_format(((int) $cents) / 100, 0, '.', ',');
    $raw = fn ($e, $f) => (int) $e->getRawOriginal($f);
    $period = \Carbon\Carbon::create((int) $year, (int) $month, 1)->locale(app()->getLocale())->translatedFormat('F Y');
    $pct = fn ($key, $default) => rtrim(rtrim(number_format((float) ($rates[$key] ?? $default), 2), '0'), '.');
    $nssfCombined = $totals['nssf_employee'] + $totals['nssf_employer'];
    $employerCost = $totals['gross'] + $totals['nssf_employer'] + $totals['wcf'] + $totals['sdl'];
    $remit = $totals['paye'] + $nssfCombined + $totals['wcf'] + $totals['sdl'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; padding: 24px 22px 14px; }
  table { width: 100%; border-collapse: collapse; }
  table.grid th { font-size: 9px; text-transform: uppercase; color: #444; background: #f1f4f2; border: 1px solid #999; padding: 4px 3px; text-align: right; }
  table.grid th.l, table.grid td.l { text-align: left; }
  table.grid td { border: 1px solid #999; padding: 3px 4px; text-align: right; }
  tr.total td { background: #f1f4f2; font-weight: bold; }
  .cards td { border: 1px solid #bbb; padding: 6px; text-align: center; width: 16.6%; }
  .cards .v { font-size: 13px; font-weight: bold; } .cards .k { font-size: 8px; color: #666; text-transform: uppercase; }
  .sec { margin-top: 10px; font-weight: bold; } .muted { color: #777; font-size: 9px; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.payroll_summary.title').' — '.$period, 'compact' => true])
<div class="muted" style="text-align:center; margin-top:2px;">{{ __('pdf.payroll_summary.subtitle', ['period' => $period]) }} · {{ __('pdf.payroll.confidential') }}</div>

<table class="cards" style="margin-top:8px;"><tr>
  <td><div class="v">{{ $entries->count() }}</div><div class="k">{{ __('pdf.payroll_summary.total_employees') }}</div></td>
  <td><div class="v">{{ $money($totals['gross']) }}</div><div class="k">{{ __('pdf.payroll_summary.total_gross') }}</div></td>
  <td><div class="v">{{ $money($totals['paye']) }}</div><div class="k">{{ __('pdf.payroll_summary.total_paye') }}</div></td>
  <td><div class="v">{{ $money($nssfCombined) }}</div><div class="k">{{ __('pdf.payroll_summary.nssf_combined') }}</div></td>
  <td><div class="v">{{ $money($totals['net']) }}</div><div class="k">{{ __('pdf.payroll_summary.total_net') }}</div></td>
  <td><div class="v">{{ $money($employerCost) }}</div><div class="k">{{ __('pdf.payroll_summary.total_employer_cost') }}</div></td>
</tr></table>

<table class="grid" style="margin-top:8px;">
  <thead><tr>
    <th class="l">#</th><th class="l">{{ __('pdf.payroll.name') }}</th><th class="l">{{ __('pdf.payroll.staff_no') }}</th>
    <th>{{ __('pdf.payroll.basic') }}</th><th>{{ __('pdf.payroll.allowances_short') }}</th><th>{{ __('pdf.payroll.gross') }}</th>
    <th>{{ __('pdf.payroll.paye') }}</th><th>{{ __('pdf.payroll.nssf_employee_short') }}</th><th>{{ __('pdf.payroll.nssf_employer_short') }}</th>
    <th>{{ __('pdf.payroll.wcf') }}</th><th>{{ __('pdf.payroll.sdl') }}</th><th>{{ __('pdf.payroll.total_deductions_short') }}</th>
    <th>{{ __('pdf.payroll.net_pay') }}</th><th class="l">{{ __('pdf.payroll.status') }}</th>
  </tr></thead>
  <tbody>
    @foreach($entries as $i => $e)
      @php $st = $e->status instanceof \BackedEnum ? $e->status->value : $e->status; @endphp
      <tr>
        <td class="l">{{ $i + 1 }}</td><td class="l">{{ $e->employee?->full_name }}</td><td class="l">{{ $e->employee?->staff_number }}</td>
        <td>{{ $money($raw($e, 'basic_salary_cents')) }}</td><td>{{ $money($raw($e, 'allowances_cents')) }}</td>
        <td>{{ $money($raw($e, 'basic_salary_cents') + $raw($e, 'allowances_cents')) }}</td>
        <td>{{ $money($raw($e, 'paye_cents')) }}</td><td>{{ $money($raw($e, 'nssf_employee_cents')) }}</td><td>{{ $money($raw($e, 'nssf_employer_cents')) }}</td>
        <td>{{ $money($raw($e, 'wcf_cents')) }}</td><td>{{ $money($raw($e, 'sdl_cents')) }}</td><td>{{ $money($raw($e, 'deductions_cents')) }}</td>
        <td>{{ $money($raw($e, 'net_salary_cents')) }}</td>
        <td class="l">{{ __('pdf.payroll.status_values.'.$st) }}</td>
      </tr>
    @endforeach
    <tr class="total">
      <td class="l" colspan="3">{{ __('pdf.payroll_summary.totals') }}</td>
      <td>{{ $money($totals['basic']) }}</td><td>{{ $money($totals['allowances']) }}</td><td>{{ $money($totals['gross']) }}</td>
      <td>{{ $money($totals['paye']) }}</td><td>{{ $money($totals['nssf_employee']) }}</td><td>{{ $money($totals['nssf_employer']) }}</td>
      <td>{{ $money($totals['wcf']) }}</td><td>{{ $money($totals['sdl']) }}</td><td>{{ $money($totals['deductions']) }}</td>
      <td>{{ $money($totals['net']) }}</td><td></td>
    </tr>
  </tbody>
</table>

<div class="sec">{{ __('pdf.payroll_summary.remittance_summary') }}</div>
<table class="grid" style="width:55%;">
  <thead><tr><th class="l">{{ __('pdf.payroll_summary.authority') }}</th><th>{{ __('pdf.payroll_summary.amount_due') }} (TZS)</th></tr></thead>
  <tbody>
    <tr><td class="l">{{ __('pdf.payroll_summary.tra_paye') }}</td><td>{{ $money($totals['paye']) }}</td></tr>
    <tr><td class="l">{{ __('pdf.payroll_summary.nssf_line', ['employee' => $pct('nssf_employee_pct', 10), 'employer' => $pct('nssf_employer_pct', 10)]) }}</td><td>{{ $money($nssfCombined) }}</td></tr>
    <tr><td class="l">{{ __('pdf.payroll_summary.wcf_line', ['rate' => $pct('wcf_pct', 0.5)]) }}</td><td>{{ $money($totals['wcf']) }}</td></tr>
    <tr><td class="l">{{ __('pdf.payroll_summary.sdl_line', ['rate' => $pct('sdl_pct', 4.5)]) }}</td><td>{{ $money($totals['sdl']) }}</td></tr>
    <tr class="total"><td class="l">{{ __('pdf.payroll_summary.total_remittances') }}</td><td>{{ $money($remit) }}</td></tr>
  </tbody>
</table>

<div class="muted" style="text-align:center; margin-top:12px;">{{ __('pdf.payroll_summary.footer', ['period' => $period, 'date' => now()->format('d/m/Y')]) }}</div>
</body>
</html>
