@php
    $lh = \App\Support\SchoolLetterhead::for($school);
    $money = fn ($cents) => number_format(((int) $cents) / 100, 0, '.', ',');
    $raw = fn ($e, $f) => (int) $e->getRawOriginal($f);
    $monthName = fn ($m) => \Carbon\Carbon::create(2000, (int) $m, 1)->locale(app()->getLocale())->translatedFormat('F');
    $gross = $totals['basic'] + $totals['allowances'];
    $totalEmployeeDed = $totals['paye'] + $totals['nssf_employee'];
    $totalEmployer = $totals['nssf_employer'] + $totals['wcf'] + $totals['sdl'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; padding: 36px 30px 20px; }
  table { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 3px 4px; } table.kv td.k { color: #555; width: 18%; } table.kv td.v { font-weight: bold; width: 32%; }
  table.grid th { font-size: 9.5px; text-transform: uppercase; color: #444; background: #f1f4f2; border: 1px solid #999; padding: 4px 3px; text-align: right; }
  table.grid th:first-child, table.grid td:first-child { text-align: left; }
  table.grid td { border: 1px solid #999; padding: 4px; text-align: right; }
  .sec { margin-top: 12px; font-weight: bold; } .muted { color: #777; font-size: 9px; }
  tr.total td { background: #f1f4f2; font-weight: bold; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.payroll_annual.title').' — '.$year, 'compact' => true])
<div class="muted" style="text-align:center; margin-top:2px;">{{ __('pdf.payroll_annual.period', ['year' => $year]) }} · {{ __('pdf.payroll.confidential') }}</div>

<div class="sec">{{ __('pdf.payroll_annual.employee_information') }}</div>
<table class="kv">
  <tr>
    <td class="k">{{ __('pdf.payroll.name') }}</td><td class="v">{{ $employee->full_name }}</td>
    <td class="k">{{ __('pdf.payroll.staff_no') }}</td><td class="v">{{ $employee->staff_number }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.payroll.department') }}</td><td class="v">{{ $employee->department ?: '—' }}</td>
    <td class="k">{{ __('pdf.payroll.role') }}</td><td class="v">{{ $employee->role ?: '—' }}</td>
  </tr>
</table>

<div class="sec">{{ __('pdf.payroll_annual.monthly_breakdown') }}</div>
<table class="grid">
  <thead><tr>
    <th>{{ __('pdf.payroll.month') }}</th><th>{{ __('pdf.payroll.basic') }}</th><th>{{ __('pdf.payroll.allowances_short') }}</th>
    <th>{{ __('pdf.payroll.gross') }}</th><th>{{ __('pdf.payroll.paye') }}</th><th>{{ __('pdf.payroll.nssf_employee_short') }}</th>
    <th>{{ __('pdf.payroll.total_deductions_short') }}</th><th>{{ __('pdf.payroll.net_pay') }}</th>
  </tr></thead>
  <tbody>
    @forelse($entries as $e)
      <tr>
        <td>{{ $monthName($e->month) }}</td>
        <td>{{ $money($raw($e, 'basic_salary_cents')) }}</td>
        <td>{{ $money($raw($e, 'allowances_cents')) }}</td>
        <td>{{ $money($raw($e, 'basic_salary_cents') + $raw($e, 'allowances_cents')) }}</td>
        <td>{{ $money($raw($e, 'paye_cents')) }}</td>
        <td>{{ $money($raw($e, 'nssf_employee_cents')) }}</td>
        <td>{{ $money($raw($e, 'deductions_cents')) }}</td>
        <td>{{ $money($raw($e, 'net_salary_cents')) }}</td>
      </tr>
    @empty
      <tr><td colspan="8" style="text-align:center; color:#777;">{{ __('pdf.payroll_annual.no_records', ['year' => $year]) }}</td></tr>
    @endforelse
    <tr class="total">
      <td>{{ __('pdf.payroll_annual.annual_totals') }}</td>
      <td>{{ $money($totals['basic']) }}</td><td>{{ $money($totals['allowances']) }}</td><td>{{ $money($gross) }}</td>
      <td>{{ $money($totals['paye']) }}</td><td>{{ $money($totals['nssf_employee']) }}</td>
      <td>{{ $money($totalEmployeeDed) }}</td><td>{{ $money($totals['net']) }}</td>
    </tr>
  </tbody>
</table>

<div class="sec">{{ __('pdf.payroll_annual.statutory_summary') }}</div>
<table class="grid" style="width:60%;">
  <thead><tr><th>{{ __('pdf.payroll_annual.deduction') }}</th><th>{{ __('pdf.payroll_annual.annual_total') }} (TZS)</th></tr></thead>
  <tbody>
    <tr><td>{{ __('pdf.payroll_annual.paye') }}</td><td>{{ $money($totals['paye']) }}</td></tr>
    <tr><td>{{ __('pdf.payroll_annual.nssf_employee') }}</td><td>{{ $money($totals['nssf_employee']) }}</td></tr>
    <tr class="total"><td>{{ __('pdf.payroll_annual.total_employee_deductions') }}</td><td>{{ $money($totalEmployeeDed) }}</td></tr>
  </tbody>
</table>

<div class="sec">{{ __('pdf.payroll_annual.employer_contributions') }}</div>
<table class="grid" style="width:60%;">
  <thead><tr><th>{{ __('pdf.payroll_annual.contribution') }}</th><th>{{ __('pdf.payroll_annual.annual_total') }} (TZS)</th></tr></thead>
  <tbody>
    <tr><td>{{ __('pdf.payroll_annual.nssf_employer') }}</td><td>{{ $money($totals['nssf_employer']) }}</td></tr>
    <tr><td>{{ __('pdf.payroll.wcf') }}</td><td>{{ $money($totals['wcf']) }}</td></tr>
    <tr><td>{{ __('pdf.payroll.sdl') }}</td><td>{{ $money($totals['sdl']) }}</td></tr>
    <tr class="total"><td>{{ __('pdf.payroll_annual.total_employer_contributions') }}</td><td>{{ $money($totalEmployer) }}</td></tr>
  </tbody>
</table>

<div class="muted" style="text-align:center; margin-top:16px;">{{ __('pdf.payroll_annual.footer', ['year' => $year, 'date' => now()->format('d/m/Y')]) }}</div>
</body>
</html>
