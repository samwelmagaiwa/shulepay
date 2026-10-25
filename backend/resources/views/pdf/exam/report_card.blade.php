<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
@include('pdf.exam.partials.styles')
</style>
</head>
<body>
  <div class="section">
    @include('pdf.exam.partials.report_card_body', ['card' => $card, 'lh' => $lh])
  </div>
</body>
</html>
