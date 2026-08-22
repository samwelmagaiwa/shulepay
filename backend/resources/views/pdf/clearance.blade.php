<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Certificate</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10.5pt;
            color: #1a1a2e;
            background: #fff;
            width: 210mm;
            height: 297mm;
            position: relative;
            overflow: hidden;
        }

        /* ── Outer decorative border ── */
        .border-outer {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 3px solid #1a3c6e;
        }
        .border-inner {
            position: absolute;
            top: 11mm;
            left: 11mm;
            right: 11mm;
            bottom: 11mm;
            border: 1px solid #c9a84c;
        }
        /* Corner ornaments */
        .corner {
            position: absolute;
            width: 18mm;
            height: 18mm;
            border-color: #c9a84c;
            border-style: solid;
        }
        .corner-tl { top: 7mm;  left: 7mm;  border-width: 4px 0 0 4px; }
        .corner-tr { top: 7mm;  right: 7mm; border-width: 4px 4px 0 0; }
        .corner-bl { bottom: 7mm; left: 7mm;  border-width: 0 0 4px 4px; }
        .corner-br { bottom: 7mm; right: 7mm; border-width: 0 4px 4px 0; }

        /* ── Page content ── */
        .page {
            position: absolute;
            top: 14mm;
            left: 14mm;
            right: 14mm;
            bottom: 14mm;
            display: flex;
            flex-direction: column;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 2px double #1a3c6e;
            margin-bottom: 10px;
        }
        .school-name {
            font-size: 17pt;
            font-weight: bold;
            color: #1a3c6e;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .school-sub {
            font-size: 8.5pt;
            color: #555;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1a3c6e;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-top: 8px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #7a6830;
            font-style: italic;
            margin-top: 2px;
            letter-spacing: 1px;
        }

        /* ── Ref line ── */
        .ref-line {
            font-size: 8pt;
            color: #888;
            text-align: right;
            margin-bottom: 10px;
        }

        /* ── Body intro ── */
        .intro {
            font-size: 10pt;
            line-height: 1.65;
            margin-bottom: 12px;
            text-align: justify;
        }
        .intro em { font-style: italic; color: #444; }

        /* ── Student row: photo + table ── */
        .student-row {
            width: 100%;
            margin-bottom: 14px;
        }
        .student-row td { vertical-align: top; border: none; padding: 0; }
        .photo-cell { width: 32mm; padding-right: 10px; }
        .photo-box {
            width: 32mm;
            height: 40mm;
            border: 1.5px solid #1a3c6e;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #aaa;
            font-size: 7.5pt;
            background: #f7f9fc;
        }
        .details-cell { width: 100%; }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td {
            padding: 5px 8px;
            border: 1px solid #cdd5e0;
            font-size: 10pt;
        }
        .details-table td.lbl {
            font-weight: bold;
            background: #f0f4fb;
            width: 42%;
            color: #1a3c6e;
        }

        /* ── Gold divider ── */
        .gold-divider {
            border: none;
            border-top: 2px solid #c9a84c;
            margin: 10px 0;
        }

        /* ── Footer note (italic) ── */
        .footer-note {
            font-size: 8.5pt;
            color: #555;
            font-style: italic;
            line-height: 1.6;
            margin-bottom: 14px;
            text-align: justify;
            border-left: 3px solid #c9a84c;
            padding-left: 8px;
        }

        /* ── Signature block ── */
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .sig-table td { border: none; vertical-align: bottom; padding: 0; }
        .sig-block { border-top: 1px solid #333; padding-top: 4px; margin-top: 36px; }
        .sig-label { font-size: 8.5pt; color: #444; }
        .sig-sub   { font-size: 7.5pt; color: #888; }
        .stamp-box {
            width: 28mm; height: 28mm;
            border: 1px dashed #bbb;
            display: inline-block;
            text-align: center;
            font-size: 7pt; color: #bbb;
            padding-top: 10mm;
            margin-top: 4px;
        }

        /* ── Watermark footer ── */
        .watermark {
            text-align: center;
            font-size: 7.5pt;
            color: #bbb;
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>

    {{-- Decorative borders --}}
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <div class="page">

        {{-- Header --}}
        <div class="header">
            <div class="school-name">{{ $school?->name ?? 'School Name' }}</div>
            <div class="school-sub">
                @if($school?->address){{ $school->address }}@endif
                @if($school?->phone) &bull; {{ $school->phone }}@endif
                @if($school?->email) &bull; {{ $school->email }}@endif
            </div>
            <div class="doc-title">Clearance Certificate</div>
            <div class="doc-subtitle">Cheti cha Usafi wa Madeni &mdash; Fee Clearance</div>
        </div>

        {{-- Ref --}}
        <div class="ref-line">
            Ref: CLR-{{ $student->id }}-{{ $academicYear->id }}-{{ $issuedAt->format('Ymd') }}
            &nbsp;&bull;&nbsp; Date: {{ $issuedAt->format('d F Y') }}
        </div>

        {{-- Intro --}}
        <div class="intro">
            This is to certify that the student whose particulars appear below has <strong>settled all school fees
            in full</strong> for the academic year indicated, and carries no outstanding financial obligation on
            the records of <strong>{{ $school?->name ?? 'this school' }}</strong>.
            <br>
            <em>Hii ni kuthibitisha kwamba mwanafunzi aliyetajwa hapa chini amefanya malipo yote ya shule kwa
            mwaka wa masomo ulioonyeshwa na hana deni lolote katika vitabu vya mahesabu ya shule.</em>
        </div>

        {{-- Student details: passport photo + table --}}
        <table class="student-row">
            <tr>
                <td class="photo-cell">
                    <div class="photo-box">
                        Passport<br>Photo
                    </div>
                </td>
                <td class="details-cell">
                    <table class="details-table">
                        <tr>
                            <td class="lbl">Full Name / Jina Kamili</td>
                            <td>{{ $student->fullName() }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Admission No. / Nambari ya Usajili</td>
                            <td>{{ $enrollment?->admission_number ?? '&mdash;' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Class / Darasa</td>
                            <td>{{ $enrollment?->schoolClass?->name ?? '&mdash;' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Academic Year / Mwaka wa Masomo</td>
                            <td>{{ $academicYear->name ?? $academicYear->year ?? $academicYear->id }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Gender / Jinsia</td>
                            <td>{{ ucfirst($student->gender ?? '&mdash;') }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Date of Birth / Tarehe ya Kuzaliwa</td>
                            <td>{{ $student->date_of_birth?->format('d/m/Y') ?? '&mdash;' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <hr class="gold-divider">

        {{-- Footer note (italic) --}}
        <div class="footer-note">
            <em>This certificate is issued solely for the purpose of confirming fee clearance. It does not
            constitute proof of academic completion, good conduct, or any other matter. It is valid only when
            bearing the official school stamp and an authorised signature.</em>
            <br>
            <em>Cheti hiki kimetolewa kwa madhumuni ya kuthibitisha usafi wa madeni ya shule peke yake. Halali tu
            ikiwa ina muhuri rasmi wa shule na saini ya mwenye mamlaka.</em>
            <br><br>
            <em>Issued by / Imetolewa na: <strong style="font-style:normal;">{{ $issuedBy?->name ?? 'System' }}</strong>
            &nbsp;&bull;&nbsp; {{ $issuedAt->format('d F Y, H:i') }}</em>
        </div>

        {{-- Signatures --}}
        <table class="sig-table">
            <tr>
                <td style="width:38%;">
                    <div class="sig-block">
                        <div class="sig-label">Accountant / Mhasibu</div>
                        <div class="sig-sub">Signature &amp; Date: _______________</div>
                    </div>
                </td>
                <td style="width:24%; text-align:center; vertical-align:bottom; padding-bottom:4px;">
                    <div class="stamp-box">Official<br>Stamp</div>
                </td>
                <td style="width:38%; text-align:right;">
                    <div class="sig-block">
                        <div class="sig-label">Principal / Mkuu wa Shule</div>
                        <div class="sig-sub">Signature &amp; Date: _______________</div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Watermark --}}
        <div class="watermark">
            Generated digitally by ShulePay &bull; {{ $issuedAt->format('Y') }} &bull;
            This document is system-verified and does not require a handwritten copy number.
        </div>

    </div>

</body>
</html>
