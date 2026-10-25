@php
    $m = fn ($c) => number_format(((int) $c) / 100, 0, '.', ',');
    $rows = $report['rows'] ?? [];
    $monthName = fn ($n) => \Carbon\Carbon::create(2000, (int) $n, 1)->locale(app()->getLocale())->translatedFormat('F');
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
    <p>{{ __('pdf.reports.expenses_vs_collections.title', ['year' => $report['year'] ?? now()->year]) }}</p>
    <p>{{ __('pdf.generated') }}: {{ now()->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</p>
</div>

<table class="grid">
    <thead>
        <tr>
            <th>{{ __('pdf.reports.expenses_vs_collections.month') }}</th>
            <th class="r">{{ __('pdf.reports.expenses_vs_collections.collections') }}</th>
            <th class="r">{{ __('pdf.reports.expenses_vs_collections.expenses') }}</th>
            <th class="r">{{ __('pdf.reports.expenses_vs_collections.net') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $monthName($r['month']) }}</td>
                <td class="r pos">{{ $m($r['collections_cents']) }}</td>
                <td class="r neg">{{ $m($r['expenses_cents']) }}</td>
                <td class="r {{ $r['net_cents'] >= 0 ? 'pos' : 'neg' }}">{{ $m($r['net_cents']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>{{ __('pdf.reports.total') }}</td>
            <td class="r">{{ $m(collect($rows)->sum('collections_cents')) }}</td>
            <td class="r">{{ $m(collect($rows)->sum('expenses_cents')) }}</td>
            <td class="r">{{ $m(collect($rows)->sum('net_cents')) }}</td>
        </tr>
    </tbody>
</table>
<div class="footer">{{ __('pdf.report_footer') }}</div>
</body>
</html>
