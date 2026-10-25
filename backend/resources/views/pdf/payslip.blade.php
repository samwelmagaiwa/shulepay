@php
    $lh = \App\Support\SchoolLetterhead::for($school);
    $raw = fn ($field) => (int) $payroll->getRawOriginal($field);
    $money = fn ($cents) => number_format(((int) $cents) / 100, 0, '.', ',');
    $basic = $raw('basic_salary_cents');
    $allow = $raw('allowances_cents');
    $gross = $basic + $allow;
    $paye = $raw('paye_cents');
    $nssfE = $raw('nssf_employee_cents');
    $totalDed = $raw('deductions_cents');
    $net = $raw('net_salary_cents');
    $emp = $payroll->employee;
    $period = \Carbon\Carbon::create((int) $payroll->year, (int) $payroll->month, 1)->locale(app()->getLocale())->translatedFormat('F Y');
    $status = $payroll->status instanceof \BackedEnum ? $payroll->status->value : $payroll->status;
    $method = $payroll->payment_method instanceof \BackedEnum ? $payroll->payment_method->value : $payroll->payment_method;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; padding: 36px 30px 20px; }
  table { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 3px 4px; vertical-align: top; }
  table.kv td.k { color: #555; width: 18%; } table.kv td.v { font-weight: bold; width: 32%; }
  table.grid th { font-size: 10px; text-transform: uppercase; color: #444; background: #f1f4f2; border: 1px solid #999; padding: 5px; text-align: left; }
  table.grid td { border: 1px solid #999; padding: 4px 6px; }
  .amt { text-align: right; } .sec { margin-top: 12px; font-weight: bold; font-size: 11px; }
  .net { border: 2px solid #007f3e; border-radius: 4px; padding: 10px; margin-top: 12px; text-align: center; }
  .net .lbl { font-size: 10px; letter-spacing: 1.2px; color: #555; } .net .v { font-size: 22px; font-weight: bold; color: #007f3e; }
  .muted { color: #777; font-size: 9px; }
</style>
</head>
<body>

@include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.payslip.title').' — '.$period, 'compact' => true])
<div class="muted" style="text-align:center; margin-top:2px;">{{ __('pdf.payslip.department_line') }} · {{ __('pdf.payroll.confidential') }}</div>

<div class="sec">{{ __('pdf.payslip.employee_details') }}</div>
<table class="kv">
  <tr>
    <td class="k">{{ __('pdf.payroll.name') }}</td><td class="v">{{ $emp->full_name }}</td>
    <td class="k">{{ __('pdf.payroll.staff_no') }}</td><td class="v">{{ $emp->staff_number }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.payroll.department') }}</td><td class="v">{{ $emp->department ?: '—' }}</td>
    <td class="k">{{ __('pdf.payroll.role') }}</td><td class="v">{{ $emp->role ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.payroll.month') }}</td><td class="v">{{ $period }}</td>
    <td class="k">{{ __('pdf.payslip.pay_method') }}</td><td class="v">{{ __('pdf.payroll.method_values.'.$method) !== 'pdf.payroll.method_values.'.$method ? __('pdf.payroll.method_values.'.$method) : ($method ?: '—') }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.payroll.status') }}</td><td class="v">{{ $status === 'paid' ? __('pdf.payroll.status_values.paid') : __('pdf.payslip.pending') }}</td>
    <td class="k">{{ __('pdf.payslip.pay_date') }}</td><td class="v">{{ $payroll->payment_date ? $payroll->payment_date->format('d/m/Y') : '—' }}</td>
  </tr>
</table>

<div class="sec">{{ __('pdf.payslip.earnings') }}</div>
<table class="grid">
  <thead><tr><th>{{ __('pdf.payslip.description') }}</th><th class="amt">{{ __('pdf.payslip.amount') }} (TZS)</th></tr></thead>
  <tbody>
    <tr><td>{{ __('pdf.payslip.basic_salary') }}</td><td class="amt">{{ $money($basic) }}</td></tr>
    <tr><td>{{ __('pdf.payslip.allowances') }}</td><td class="amt">{{ $money($allow) }}</td></tr>
    <tr style="background:#f7faf8;"><td><strong>{{ __('pdf.payslip.gross_salary') }}</strong></td><td class="amt"><strong>{{ $money($gross) }}</strong></td></tr>
  </tbody>
</table>

<div class="sec">{{ __('pdf.payslip.statutory_deductions') }}</div>
<table class="grid">
  <thead><tr><th>{{ __('pdf.payslip.description') }}</th><th>{{ __('pdf.payslip.rate') }}</th><th class="amt">{{ __('pdf.payslip.amount') }} (TZS)</th></tr></thead>
  <tbody>
    <tr><td>{{ __('pdf.payslip.paye') }}</td><td>{{ __('pdf.payslip.graduated') }}</td><td class="amt">{{ $money($paye) }}</td></tr>
    <tr><td>{{ __('pdf.payslip.nssf_employee') }}</td><td>{{ rtrim(rtrim(number_format((float) ($rates['nssf_employee_pct'] ?? 10), 2), '0'), '.') }}%</td><td class="amt">{{ $money($nssfE) }}</td></tr>
    <tr style="background:#f7faf8;"><td colspan="2"><strong>{{ __('pdf.payslip.total_employee_deductions') }}</strong></td><td class="amt"><strong>{{ $money($totalDed) }}</strong></td></tr>
  </tbody>
</table>

<div class="net">
  <div class="lbl">{{ __('pdf.payslip.net_pay') }}</div>
  <div class="v">TZS {{ $money($net) }}</div>
</div>

<div class="sec">{{ __('pdf.payslip.employer_contributions') }}</div>
<table class="grid">
  <tbody>
    <tr><td>{{ __('pdf.payslip.nssf_employer') }}</td><td>{{ rtrim(rtrim(number_format((float) ($rates['nssf_employer_pct'] ?? 10), 2), '0'), '.') }}%</td><td class="amt">{{ $money($raw('nssf_employer_cents')) }}</td></tr>
    <tr><td>{{ __('pdf.payslip.wcf') }}</td><td>{{ rtrim(rtrim(number_format((float) ($rates['wcf_pct'] ?? 0.5), 2), '0'), '.') }}%</td><td class="amt">{{ $money($raw('wcf_cents')) }}</td></tr>
    <tr><td>{{ __('pdf.payslip.sdl') }}</td><td>{{ rtrim(rtrim(number_format((float) ($rates['sdl_pct'] ?? 4.5), 2), '0'), '.') }}%</td><td class="amt">{{ $money($raw('sdl_cents')) }}</td></tr>
  </tbody>
</table>

@if(! empty($payroll->notes))
  <div class="sec">{{ __('pdf.payslip.notes') }}</div>
  <p>{{ $payroll->notes }}</p>
@endif

<table style="margin-top:28px;"><tr>
  <td style="width:50%; font-size:10px;">{{ __('pdf.payroll.employee_signature') }}: ____________________</td>
  <td style="width:50%; font-size:10px;">{{ __('pdf.payroll.authorized_signatory') }}: ____________________</td>
</tr></table>
<div class="muted" style="text-align:center; margin-top:14px;">{{ __('pdf.payslip.footer', ['date' => now()->format('d/m/Y')]) }}</div>

</body>
</html>
