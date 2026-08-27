{{--
  Shared document letterhead.

  Expects $lh from App\Support\SchoolLetterhead::for($school) and an optional
  $docTitle. Laid out with tables, not flexbox — DomPDF does not implement
  flexbox, and an earlier version of the receipt silently rendered its rows
  stacked because of it.

  $compact = true tightens it for A5 receipts; the default suits A4.
--}}
@php
    $compact = $compact ?? false;
    $scale = $compact ? 0.82 : 1.0;
    $px = fn ($n) => round($n * $scale, 1).'px';
@endphp

<table style="width:100%; border-collapse:collapse;">
  <tr>
    {{-- Logo --}}
    @if(!empty($lh['logo']))
      <td style="width:{{ $px(74) }}; vertical-align:middle; padding-right:{{ $px(12) }};">
        <img src="{{ $lh['logo'] }}" style="max-width:{{ $px(70) }}; max-height:{{ $px(70) }};" alt="">
      </td>
    @endif

    {{-- School identity --}}
    <td style="vertical-align:middle;">
      <div style="font-size:{{ $px(20) }}; font-weight:bold; color:#007f3e; letter-spacing:.5px; line-height:1.15;">
        {{ strtoupper($lh['name']) }}
      </div>
      @if(!empty($lh['motto']))
        <div style="font-size:{{ $px(9) }}; color:#666; font-style:italic; margin-top:{{ $px(2) }};">
          {{ $lh['motto'] }}
        </div>
      @elseif(!empty($lh['tagline']))
        <div style="font-size:{{ $px(9) }}; color:#666; letter-spacing:1.5px; text-transform:uppercase; margin-top:{{ $px(2) }};">
          {{ $lh['tagline'] }}
        </div>
      @endif
    </td>

    {{-- Postal + contact block, right aligned like a printed letterhead --}}
    <td style="vertical-align:middle; text-align:right; font-size:{{ $px(8.5) }}; color:#444; line-height:1.5;">
      @foreach($lh['address_lines'] as $line)
        <div>{{ $line }}</div>
      @endforeach
      @foreach($lh['phones'] as $i => $phone)
        <div>{{ $i === 0 ? 'Tel: ' : '' }}{{ $phone }}</div>
      @endforeach
      @if(!empty($lh['email']))
        <div>{{ $lh['email'] }}</div>
      @endif
      @if(!empty($lh['website']))
        <div>{{ $lh['website'] }}</div>
      @endif
    </td>
  </tr>
</table>

{{-- Double rule: a thick brand bar over a hairline, the usual letterhead device --}}
<div style="border-top:{{ $compact ? '2px' : '2.5px' }} solid #007f3e; margin-top:{{ $px(8) }};"></div>
<div style="border-top:0.7px solid #c8c8c8; margin-top:{{ $px(1.5) }};"></div>

@isset($docTitle)
  <div style="text-align:center; margin-top:{{ $px(10) }};">
    <span style="font-size:{{ $px(11) }}; font-weight:bold; letter-spacing:{{ $px(3) }};
                 color:#222; border-bottom:1.5px solid #007f3e; padding-bottom:{{ $px(2) }};">
      {{ strtoupper($docTitle) }}
    </span>
  </div>
@endisset
