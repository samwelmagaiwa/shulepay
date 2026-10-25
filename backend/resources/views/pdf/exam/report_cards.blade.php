<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
@include('pdf.exam.partials.styles')
</style>
</head>
<body>
  {{-- One card per page. The break comes from the loop, not a CSS selector, and the page padding sits
       on each section because body padding only applies to the first page in DomPDF. --}}
  @foreach($cards as $card)
    <div class="section {{ $loop->last ? '' : 'page-break' }}">
      @include('pdf.exam.partials.report_card_body', ['card' => $card, 'lh' => $lh])
    </div>
  @endforeach
</body>
</html>
