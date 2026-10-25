{{--
  One pupil's exam report card — the content between <body> tags, shared by report_card
  (a single pupil) and report_cards (a whole class, one card per page).
  Expects $card (ExamResultsService::reportCard()) and $lh (SchoolLetterhead::for()).
--}}
@php
    $exam = $card['exam'];
    $student = $card['student'];
    $sum = $card['summary'];
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');
    $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
@endphp

@include('pdf.partials.letterhead', [
  'lh' => $lh,
  'docTitle' => __('pdf.exam.report_card').' — '.$exam['name'],
  'compact' => true,
])

<table class="kv" style="margin-top:8px;">
  <tr>
    <td class="k">{{ __('pdf.exam.student') }}</td><td class="v">{{ $student['full_name'] }}</td>
    <td class="k">{{ __('pdf.exam.admission_no') }}</td><td class="v">{{ $student['admission_number'] ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.exam.class') }}</td><td class="v">{{ $student['class'] }}</td>
    <td class="k">{{ __('pdf.exam.term') }}</td><td class="v">{{ $exam['term'] ?: '—' }}</td>
  </tr>
  <tr>
    <td class="k">{{ __('pdf.exam.exam') }}</td><td class="v">{{ $exam['name'] }}</td>
    <td class="k">{{ __('pdf.exam.academic_year') }}</td><td class="v">{{ $exam['academic_year'] ?: '—' }}</td>
  </tr>
</table>

<table class="grid" style="margin-top:8px;">
  <thead>
    <tr>
      <th style="text-align:left;">{{ __('pdf.exam.subject') }}</th>
      <th>{{ __('pdf.exam.marks') }}</th>
      <th>{{ __('pdf.exam.grade') }}</th>
      <th>{{ __('pdf.exam.position') }}</th>
      <th style="text-align:left;">{{ __('pdf.exam.remarks') }}</th>
    </tr>
  </thead>
  <tbody>
    @forelse($card['subjects'] as $s)
      <tr>
        <td style="text-align:left;">{{ $s['name'] }}</td>
        @if($s['absent'])
          <td colspan="4" style="text-align:left; color:#777;"><em>{{ __('pdf.exam.did_not_sit') }}</em></td>
        @else
          <td>{{ $s['marks'] === null ? '—' : $fmt($s['marks']) }}</td>
          <td><strong>{{ $s['grade'] ?: '—' }}</strong></td>
          <td>{{ $s['position'] ?: '—' }}</td>
          <td style="text-align:left;">{{ $s['remark'] ?: '' }}</td>
        @endif
      </tr>
    @empty
      <tr><td colspan="5" style="text-align:center; color:#777;">{{ __('pdf.exam.no_subjects') }}</td></tr>
    @endforelse
  </tbody>
</table>

<table class="grid summary" style="margin-top:8px;">
  <tr>
    <td>{{ __('pdf.exam.total') }}: <strong>{{ $fmt($sum['total']) }}</strong></td>
    <td>{{ __('pdf.exam.average') }}: <strong>{{ $sum['average'] === null ? '—' : $fmt($sum['average']).'%' }}</strong></td>
    <td>{{ __('pdf.exam.grade') }}: <strong>{{ $sum['grade'] ?: '—' }}</strong></td>
    <td>{{ __('pdf.exam.position') }}: <strong>{{ $sum['position'] ? $sum['position'].' / '.$sum['ranked'] : '—' }}</strong></td>
    @if($sum['total_points'] !== null)
      <td>{{ __('pdf.exam.points') }}: <strong>{{ $sum['total_points'] }}</strong></td>
    @endif
  </tr>
</table>

@if($sum['remark'])
  <p style="margin-top:6px;"><strong>{{ __('pdf.exam.overall_remark') }}</strong> {{ $sum['remark'] }}</p>
@endif
@if($sum['division'])
  <p style="margin-top:3px;"><strong>{{ __('pdf.exam.division') }}:</strong>
    {{ __('pdf.exam.division_line', ['label' => $sum['division']['label'], 'points' => $sum['division']['points']]) }}</p>
@endif

@if($card['attendance'])
  <p style="margin-top:3px;"><strong>{{ __('pdf.exam.attendance') }}</strong>
    {{ __('pdf.exam.attendance_line', ['present' => $card['attendance']['present'] + $card['attendance']['late'], 'days' => $card['attendance']['days'], 'percent' => $card['attendance']['percent'], 'absent' => $card['attendance']['absent']]) }}</p>
@endif

<p style="margin-top:3px; color:#555; font-size:9px;">{{ __('pdf.exam.percent_note', ['total' => $exam['out_of']]) }}</p>

@if($card['comments']['teacher'] || $card['comments']['head'])
  <table class="comments" style="margin-top:8px;">
    @if($card['comments']['teacher'])
      <tr><td><strong>{{ __('pdf.exam.teacher_comment') }}</strong> {{ $card['comments']['teacher'] }}</td></tr>
    @endif
    @if($card['comments']['head'])
      <tr><td><strong>{{ __('pdf.exam.head_comment') }}</strong> {{ $card['comments']['head'] }}</td></tr>
    @endif
  </table>
@endif

@if($exam['next_term_opens'])
  <p style="margin-top:6px;"><strong>{{ __('pdf.exam.next_term') }}</strong> {{ $fmtDate($exam['next_term_opens']) }}</p>
@endif

@if(!empty($card['legend']))
  <table class="grid legend" style="margin-top:8px; width:60%;">
    <thead><tr>
      <th>{{ __('pdf.exam.grade') }}</th><th>{{ __('pdf.exam.range') }}</th><th style="text-align:left;">{{ __('pdf.exam.meaning') }}</th>
    </tr></thead>
    <tbody>
      @foreach($card['legend'] as $band)
        <tr>
          <td><strong>{{ $band['grade'] }}</strong></td>
          <td>{{ $fmt($band['from']) }} – {{ $fmt($band['to']) }}</td>
          <td style="text-align:left;">{{ $band['remark'] ?: '' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

<table class="sign" style="margin-top:26px;">
  <tr>
    <td>{{ __('pdf.exam.class_teacher') }}: ____________________</td>
    <td>{{ __('pdf.exam.head_teacher') }}: ____________________</td>
    <td>{{ __('pdf.exam.guardian') }}: ____________________</td>
  </tr>
</table>
<p style="margin-top:8px; text-align:right; color:#777; font-size:9px;">{{ __('pdf.exam.issued', ['date' => now()->format('d/m/Y')]) }}</p>
