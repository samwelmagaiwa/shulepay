@php
    $exam = $results['exam'];
    $class = $results['class'];
    $sum = $results['summary'];
    $subjects = $results['subjects'];
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
@include('pdf.exam.partials.styles')
.section { padding: 24px 22px 14px; }
table.grid td.name { text-align: left; }
table.grid th, table.grid td { font-size: 9.5px; padding: 3px 3px; }
</style>
</head>
<body>
  <div class="section">
    @include('pdf.partials.letterhead', ['lh' => $lh, 'docTitle' => __('pdf.exam.class_results').' — '.$exam['name'], 'compact' => true])

    <table class="kv" style="margin-top:6px;">
      <tr>
        <td class="k">{{ __('pdf.exam.exam') }}</td><td class="v">{{ $exam['name'] }}</td>
        <td class="k">{{ __('pdf.exam.class') }}</td><td class="v">{{ $class['label'] }}</td>
        <td class="k">{{ __('pdf.exam.term') }}</td><td class="v">{{ $exam['term'] ?: '—' }}</td>
        <td class="k">{{ __('pdf.exam.year') }}</td><td class="v">{{ $exam['academic_year'] ?: '—' }}</td>
      </tr>
      <tr>
        <td class="k">{{ __('pdf.exam.students') }}</td><td class="v">{{ $sum['class_size'] }}</td>
        <td class="k">{{ __('pdf.exam.class_average') }}</td><td class="v">{{ $sum['class_average'] === null ? '—' : $fmt($sum['class_average']).'%' }}</td>
        <td colspan="4"></td>
      </tr>
    </table>

    <table class="grid" style="margin-top:6px;">
      <thead>
        <tr>
          <th>#</th>
          <th style="text-align:left;">{{ __('pdf.exam.name') }}</th>
          @foreach($subjects as $s)
            <th>{{ $s['code'] ?: $s['name'] }}</th>
          @endforeach
          <th>{{ __('pdf.exam.total') }}</th>
          <th>{{ __('pdf.exam.average') }}</th>
          <th>{{ __('pdf.exam.grade') }}</th>
          <th>{{ __('pdf.exam.position') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($results['rows'] as $i => $row)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td class="name">{{ $row['full_name'] }}</td>
            @foreach($subjects as $s)
              @php $cell = $row['cells'][$s['id']] ?? null; @endphp
              <td>
                @if($cell && $cell['absent'])
                  {{ __('pdf.exam.absent_short') }}
                @else
                  {{ $cell && $cell['marks'] !== null ? $fmt($cell['marks']) : '—' }}
                @endif
              </td>
            @endforeach
            <td>{{ $fmt($row['total']) }}</td>
            <td>{{ $fmt($row['average']) }}</td>
            <td><strong>{{ $row['grade'] ?: '—' }}</strong></td>
            <td>{{ $row['position'] ?: '—' }}</td>
          </tr>
        @endforeach
        <tr style="background:#f1f4f2;">
          <td></td>
          <td class="name"><strong>{{ __('pdf.exam.subject_average') }}</strong></td>
          @foreach($subjects as $s)
            <td><strong>{{ $fmt($s['average']) }}</strong></td>
          @endforeach
          <td colspan="4"></td>
        </tr>
      </tbody>
    </table>

    @if(!empty($sum['grade_distribution']))
      <p style="margin-top:6px;"><strong>{{ __('pdf.exam.grade_distribution') }}</strong>
        @foreach($sum['grade_distribution'] as $grade => $n){{ $grade }}: {{ $n }}@if(! $loop->last) · @endif @endforeach
      </p>
    @endif
    <p style="margin-top:6px; color:#777; font-size:9px;">{{ __('pdf.exam.percent_note', ['total' => $exam['out_of']]) }} · {{ __('pdf.exam.issued', ['date' => now()->format('d/m/Y')]) }}</p>
  </div>
</body>
</html>
