{{--
  Shared document letterhead.

  Layout:
        ┌──────────────── SCHOOL NAME (centred) ────────────────┐
        │                     motto / tagline                    │
        ├───────────────┬───────────────┬───────────────────────┤
        │  Tel / email  │     LOGO      │    postal address     │
        └───────────────┴───────────────┴───────────────────────┘
                 ══════ brand rule ══════
                       DOCUMENT TITLE

  Expects $lh from App\Support\SchoolLetterhead::for($school), plus optional
  $docTitle and $compact.

  Built entirely from tables: DomPDF does not implement flexbox, and an earlier
  version of the receipt silently rendered its rows stacked because of it.
--}}
@php
    $compact = $compact ?? false;
    $scale = $compact ? 0.85 : 1.0;
    $px = fn ($n) => round($n * $scale, 1).'px';

    $hasSides = ! empty($lh['phones']) || ! empty($lh['email'])
             || ! empty($lh['website']) || ! empty($lh['address_lines']);
@endphp

{{-- ── School name, centred across the full width ─────────────────────── --}}
<div style="text-align:center;">
  <div style="font-size:{{ $px(22) }}; font-weight:bold; color:#007f3e;
              letter-spacing:{{ $px(0.6) }}; line-height:1.15;">
    {{ strtoupper($lh['name']) }}
  </div>
  @if(!empty($lh['motto']))
    <div style="font-size:{{ $px(9.5) }}; color:#555; font-style:italic; margin-top:{{ $px(2) }};">
      {{ $lh['motto'] }}
    </div>
  @elseif(!empty($lh['tagline']))
    <div style="font-size:{{ $px(9.5) }}; color:#555; letter-spacing:{{ $px(1.6) }};
                text-transform:uppercase; margin-top:{{ $px(2) }};">
      {{ $lh['tagline'] }}
    </div>
  @endif
</div>

{{-- ── Contacts (left) · logo (centre) · postal address (right) ────────── --}}
@if($hasSides || !empty($lh['logo']))
<table style="width:100%; border-collapse:collapse; margin-top:{{ $px(8) }};">
  <tr>
    {{-- Left: how to reach the school --}}
    <td style="width:37%; vertical-align:middle; text-align:left;
               font-size:{{ $px(8.5) }}; color:#444; line-height:1.55;">
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

    {{-- Centre: logo, sitting between the two blocks --}}
    <td style="width:26%; vertical-align:middle; text-align:center;">
      @if(!empty($lh['logo']))
        <img src="{{ $lh['logo'] }}"
             style="max-width:{{ $px(78) }}; max-height:{{ $px(78) }};" alt="">
      @endif
    </td>

    {{-- Right: where the school is --}}
    <td style="width:37%; vertical-align:middle; text-align:right;
               font-size:{{ $px(8.5) }}; color:#444; line-height:1.55;">
      @foreach($lh['address_lines'] as $line)
        <div>{{ $line }}</div>
      @endforeach
    </td>
  </tr>
</table>
@endif

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
