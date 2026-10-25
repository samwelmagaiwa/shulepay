@php
    $m = fn ($c) => number_format(((int) $c) / 100, 0, '.', ',');
    $rows = $report['rows'] ?? [];
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
    <p>{{ __('pdf.reports.by_class.title') }}</p>
    <p>{{ __('pdf.generated') }}: {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</div>

<table class="grid">
    <thead>
        <tr>
            <th>{{ __('pdf.reports.by_class.class') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.students') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.billed') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.collected') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.outstanding') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.paid') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.partial') }}</th>
            <th class="r">{{ __('pdf.reports.by_class.unpaid') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ $r['class_name'] }}</td>
                <td class="r">{{ $r['student_count'] }}</td>
                <td class="r">{{ $m($r['total_billed_cents']) }}</td>
                <td class="r pos">{{ $m($r['total_collected_cents']) }}</td>
                <td class="r neg">{{ $m($r['total_outstanding_cents']) }}</td>
                <td class="r">{{ $r['paid_count'] }}</td>
                <td class="r">{{ $r['partial_count'] }}</td>
                <td class="r">{{ $r['unpaid_count'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center; color:#777;">{{ __('pdf.reports.no_data') }}</td></tr>
        @endforelse
        @if(count($rows))
            <tr class="total">
                <td>{{ __('pdf.reports.total') }}</td>
                <td class="r">{{ collect($rows)->sum('student_count') }}</td>
                <td class="r">{{ $m(collect($rows)->sum('total_billed_cents')) }}</td>
                <td class="r">{{ $m(collect($rows)->sum('total_collected_cents')) }}</td>
                <td class="r">{{ $m(collect($rows)->sum('total_outstanding_cents')) }}</td>
                <td class="r">{{ collect($rows)->sum('paid_count') }}</td>
                <td class="r">{{ collect($rows)->sum('partial_count') }}</td>
                <td class="r">{{ collect($rows)->sum('unpaid_count') }}</td>
            </tr>
        @endif
    </tbody>
</table>
<div class="footer">{{ __('pdf.report_footer') }}</div>
</body>
</html>
