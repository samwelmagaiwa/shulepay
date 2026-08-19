<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <title>Cheti cha Usafi wa Madeni / Clearance Certificate</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12pt;
            color: #1a1a1a;
            background: #fff;
            padding: 30px 40px;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #1a3c6e;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .school-name {
            font-size: 20pt;
            font-weight: bold;
            color: #1a3c6e;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .school-sub {
            font-size: 10pt;
            color: #555;
            margin-top: 4px;
        }
        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            margin-top: 12px;
            color: #2c5f2e;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .sub-title {
            font-size: 10pt;
            color: #666;
            font-style: italic;
        }
        .cert-number {
            text-align: right;
            font-size: 9pt;
            color: #888;
            margin-bottom: 18px;
        }
        .intro {
            font-size: 11pt;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .student-table td {
            padding: 7px 10px;
            border: 1px solid #ccc;
            font-size: 11pt;
        }
        .student-table td:first-child {
            font-weight: bold;
            background: #f4f4f4;
            width: 38%;
        }
        .clearance-box {
            border: 2px solid #2c5f2e;
            background: #f0faf0;
            border-radius: 4px;
            padding: 16px 20px;
            margin-bottom: 24px;
            text-align: center;
        }
        .clearance-box .sw {
            font-size: 13pt;
            font-weight: bold;
            color: #2c5f2e;
        }
        .clearance-box .en {
            font-size: 11pt;
            color: #444;
            margin-top: 6px;
        }
        .footer-note {
            font-size: 9pt;
            color: #666;
            font-style: italic;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .signature-row {
            display: table;
            width: 100%;
            margin-top: 40px;
        }
        .sig-block {
            display: table-cell;
            width: 45%;
            vertical-align: bottom;
        }
        .sig-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 6px;
        }
        .sig-label {
            font-size: 9pt;
            color: #555;
        }
        .watermark {
            text-align: center;
            font-size: 9pt;
            color: #aaa;
            margin-top: 50px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
        .date-issued {
            font-size: 11pt;
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="school-name">{{ $school?->name ?? 'Shule / School' }}</div>
        <div class="school-sub">
            @if($school?->address){{ $school->address }}@endif
            @if($school?->phone) &bull; {{ $school->phone }}@endif
            @if($school?->email) &bull; {{ $school->email }}@endif
        </div>
        <div class="doc-title">Cheti cha Usafi wa Madeni</div>
        <div class="sub-title">Clearance Certificate — Fee Clearance</div>
    </div>

    {{-- Certificate serial reference --}}
    <div class="cert-number">
        Nambari / Ref: CLR-{{ $student->id }}-{{ $academicYear->id }}-{{ $issuedAt->format('Ymd') }}
        &nbsp;&bull;&nbsp; Tarehe / Date: {{ $issuedAt->format('d F Y') }}
    </div>

    {{-- Intro paragraph --}}
    <div class="intro">
        Hii ni kuthibitisha kwamba mwanafunzi aliyetajwa hapa chini <strong>amefanya malipo yote ya shule</strong>
        kwa mwaka wa masomo ulioonyeshwa na hana deni lolote linalosimama katika vitabu vya mahesabu ya shule.
        <br><br>
        <em>This is to certify that the student named below has <strong>settled all school fees</strong> for the
        academic year indicated and has no outstanding financial obligation on the school's records.</em>
    </div>

    {{-- Student details table --}}
    <table class="student-table">
        <tr>
            <td>Jina Kamili / Full Name</td>
            <td>{{ $student->fullName() }}</td>
        </tr>
        <tr>
            <td>Nambari ya Usajili / Admission No.</td>
            <td>{{ $enrollment?->admission_number ?? '—' }}</td>
        </tr>
        <tr>
            <td>Darasa / Class</td>
            <td>{{ $enrollment?->schoolClass?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td>Mwaka wa Masomo / Academic Year</td>
            <td>{{ $academicYear->name ?? $academicYear->year ?? $academicYear->id }}</td>
        </tr>
        <tr>
            <td>Jinsia / Gender</td>
            <td>{{ ucfirst($student->gender ?? '—') }}</td>
        </tr>
        <tr>
            <td>Tarehe ya Kuzaliwa / Date of Birth</td>
            <td>{{ $student->date_of_birth?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    </table>

    {{-- Clearance declaration --}}
    <div class="clearance-box">
        <div class="sw">✓ AMEFUTA DENI ZOTE — AMESAFI</div>
        <div class="en">✓ ALL FEES CLEARED — NO OUTSTANDING BALANCE</div>
    </div>

    {{-- Footer note --}}
    <div class="footer-note">
        Cheti hiki kimetolewa kwa madhumuni ya kuthibitisha usafi wa madeni ya shule peke yake. Haitumiwi kama
        uthibitisho wa chochote kingine. Halali tu ikiwa ina muhuri rasmi wa shule na saini ya mwenye mamlaka.
        <br>
        <em>This certificate is issued solely for the purpose of confirming fee clearance. It is valid only when
        bearing the official school stamp and authorised signature.</em>
        <br><br>
        Imetolewa na / Issued by: <strong>{{ $issuedBy?->name ?? 'System' }}</strong>
        &nbsp;&bull;&nbsp; {{ $issuedAt->format('d F Y, H:i') }}
    </div>

    {{-- Signature lines --}}
    <div style="width:100%; margin-top:40px;">
        <table style="width:100%; border:none;">
            <tr>
                <td style="width:45%; border:none; vertical-align:bottom; padding:0 10px 0 0;">
                    <div style="border-top:1px solid #333; margin-top:50px; padding-top:6px;">
                        <div style="font-size:9pt; color:#555;">Saini ya Mhasibu / Accountant Signature</div>
                        <div style="font-size:9pt; color:#888;">Tarehe / Date: _______________</div>
                    </div>
                </td>
                <td style="width:10%; border:none;"></td>
                <td style="width:45%; border:none; vertical-align:bottom; padding:0 0 0 10px;">
                    <div style="border-top:1px solid #333; margin-top:50px; padding-top:6px;">
                        <div style="font-size:9pt; color:#555;">Muhuri wa Shule / School Stamp &amp; Principal</div>
                        <div style="font-size:9pt; color:#888;">Tarehe / Date: _______________</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Watermark footer --}}
    <div class="watermark">
        Hati hii imetolewa kwa njia ya mfumo wa kidijitali wa ShulePay &bull;
        This document was generated digitally by ShulePay &bull;
        {{ $issuedAt->format('Y') }}
    </div>

</body>
</html>
