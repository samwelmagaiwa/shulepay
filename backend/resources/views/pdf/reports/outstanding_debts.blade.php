@php
    $m = fn ($c) => number_format(((int) $c) / 100, 0, '.', ',');
    $rows = $report['rows'] ?? [];
    $total = collect($rows)->sum('debt_cents');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
@include('pdf.reports._style')
</style>
</head>
<body>
<div class="header">
    <h1>{{ $school?->name ?? 'ShulePay' }}</h1>
    <p>{{ __('pdf.reports.outstanding_debts.title') }}</p>
    <p>{{ __('pdf.reports.outstanding_debts.total_outstanding', ['count' => count($rows)]) }}: TZS {{ $m($total) }}</p>
    <p>{{ __('pdf.generated') }}: {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</div>

<table class="grid">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('pdf.reports.outstanding_debts.student_name') }}</th>
            <th>{{ __('pdf.reports.outstanding_debts.class') }}</th>
            <th>{{ __('pdf.reports.outstanding_debts.guardian') }}</th>
            <th>{{ __('pdf.reports.outstanding_debts.phone') }}</th>
            <th>{{ __('pdf.reports.outstanding_debts.village_street') }}</th>
            <th>{{ __('pdf.reports.outstanding_debts.terms_unpaid') }}</th>
            <th class="r">{{ __('pdf.reports.outstanding_debts.debt') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $r['student_name'] }}</td>
                <td>{{ $r['student_class'] }}</td>
                <td>{{ $r['guardian_name'] }}</td>
                <td>{{ $r['guardian_phone'] }}</td>
                <td>{{ $r['village_street'] }}</td>
                <td>{{ is_array($r['terms'] ?? null) ? implode(', ', $r['terms']) : ($r['terms'] ?? '') }}</td>
                <td class="r neg">{{ $m($r['debt_cents']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; color:#777;">{{ __('pdf.reports.outstanding_debts.none') }}</td></tr>
        @endforelse
        @if(count($rows))
            <tr class="total"><td colspan="7">{{ __('pdf.reports.total') }}</td><td class="r">{{ $m($total) }}</td></tr>
        @endif
    </tbody>
</table>
<div class="footer">{{ __('pdf.report_footer') }}</div>
</body>
</html>
