<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; padding: 12px; }
  .center { text-align: center; }
  .bold   { font-weight: bold; }
  .hr     { border-top: 1px dashed #555; margin: 6px 0; }
  .row    { display: flex; justify-content: space-between; margin: 3px 0; }
  .logo-img { max-width: 70px; max-height: 50px; margin-bottom: 4px; }
  .app-name { font-size: 15px; font-weight: bold; color: #007f3e; }
  .sub    { font-size: 8px; color: #555; }
  .receipt-no { font-size: 12px; font-weight: bold; margin: 4px 0; }
  .amount { font-size: 16px; font-weight: bold; color: #007f3e; text-align: center; margin: 6px 0; }
  .footer { font-size: 8px; color: #777; text-align: center; margin-top: 8px; }
</style>
</head>
<body>
  <div class="center">
    @if($logoBase64)
    <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo" /><br>
    @endif
    <div class="app-name">{{ $appName }}</div>
    <div class="sub">{{ $appTagline }}</div>
    @if($receipt->student?->school && $receipt->student->school->name !== $appName)
    <div class="sub bold">{{ $receipt->student->school->name }}</div>
    @endif
  </div>

  <div class="hr"></div>

  <div class="center">
    <div class="sub">RISITI YA MALIPO</div>
    <div class="receipt-no">{{ $receipt->receipt_number }}</div>
    <div class="sub">{{ $receipt->issued_at?->format('d/m/Y H:i') }}</div>
  </div>

  <div class="hr"></div>

  <div class="row">
    <span>Mwanafunzi:</span>
    <span class="bold">{{ $receipt->student?->fullName() }}</span>
  </div>
  <div class="row">
    <span>Namba:</span>
    <span>{{ $receipt->student?->admission_number }}</span>
  </div>

  @if($receipt->payment)
  <div class="hr"></div>

  <div class="row">
    <span>Ankara:</span>
    <span>{{ $receipt->payment->invoice?->invoice_number }}</span>
  </div>
  <div class="row">
    <span>Muhula:</span>
    <span>{{ $receipt->payment->invoice?->term?->name }}</span>
  </div>
  <div class="row">
    <span>Njia ya Malipo:</span>
    <span>{{ $receipt->payment->method->label() }}</span>
  </div>
  @if($receipt->payment->reference_number)
  <div class="row">
    <span>Kumbukumbu:</span>
    <span>{{ $receipt->payment->reference_number }}</span>
  </div>
  @endif

  <div class="hr"></div>

  <div class="amount">
    TZS {{ number_format($receipt->payment->amount_cents->cents() / 100, 0, '.', ',') }}
  </div>
  @endif

  <div class="hr"></div>
  <div class="footer">
    Asante kwa malipo yako. Hati hii ni ushahidi wa malipo.<br>
    {{ $appName }} &copy; {{ date('Y') }} {{ $appTagline }}
  </div>
</body>
</html>
