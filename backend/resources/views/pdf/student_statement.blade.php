{{--
  Consolidated student statement/receipt — every invoice for one student on
  a single printout. The actual content lives in
  pdf/partials/student_statement_body.blade.php, shared with
  pdf/bulk_invoices.blade.php so both produce the identical design — this
  file just supplies the one-student HTML document wrapper.
--}}
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  /* Top padding pushes the letterhead down from the page edge so a short
     statement (few invoices) doesn't read as all crammed into the top
     third of an A4 sheet with the rest left empty. */
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #111; padding: 60px 32px 24px; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .hr     { border-top: 1px dashed #555; margin: 9px 0; }
  .hr-solid { border-top: 1.5px dashed #111; margin: 8px 0; }
  .logo-img { max-width: 90px; max-height: 60px; margin-bottom: 3px; }
  .app-name { font-size: 19px; font-weight: bold; color: #007f3e; letter-spacing: .5px; }
  .sub    { font-size: 12px; color: #555; }
  .doc-title { font-size: 13px; letter-spacing: 3px; color: #333; }

  table.kv { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 4px 0; vertical-align: top; font-size: 13px; }
  table.kv td.k { color: #555; }
  table.kv td.v { text-align: right; font-weight: bold; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.items th { font-size: 10.5px; letter-spacing: .4px; text-transform: uppercase; color: #555;
                   border-bottom: 1.5px solid #999; padding: 5px 3px; text-align: left; }
  table.items th.amt, table.items td.amt { text-align: right; }
  table.items td { font-size: 12px; padding: 5px 3px; border-bottom: 1.5px dashed #888; vertical-align: top; }

  .status-paid    { color: #007f3e; font-weight: bold; }
  .status-partial { color: #b45309; font-weight: bold; }
  .status-unpaid  { color: #c0292b; font-weight: bold; }
  .row-meta { display: block; font-size: 9.5px; color: #777; margin-top: 1px; }
  table.items.dense .row-meta { display: inline; margin-left: 4px; }

  /* Applied once a student has enough invoices that full-detail rows would
     push the totals onto a second page. */
  table.items.dense th { font-size: 9px; padding: 3px 3px; }
  table.items.dense td { font-size: 10.5px; padding: 2.5px 3px; }
  table.items.dense .status-paid,
  table.items.dense .status-partial,
  table.items.dense .status-unpaid { display: inline; font-size: 10px; }

  .amount-box { border: 2px solid #007f3e; border-radius: 4px; padding: 12px; margin: 16px 0 10px; }
  .amount-lbl { font-size: 11px; letter-spacing: 1.5px; color: #555; text-align: center; }
  .amount { font-size: 25px; font-weight: bold; text-align: center; margin-top: 2px; }
  .balance-paid { color: #007f3e; }
  .balance-due  { color: #c0292b; }

  .footer { font-size: 11px; color: #777; text-align: center; margin-top: 16px; line-height: 1.5; }
</style>
</head>
<body>

@include('pdf.partials.student_statement_body', [
  'lh' => $lh,
  'statementNumber' => $statementNumber,
  'student' => $student,
  'enrollment' => $enrollment,
  'invoices' => $invoices,
  'totalInvoiced' => $totalInvoiced,
  'totalPaid' => $totalPaid,
  'totalBalance' => $totalBalance,
  'appName' => $appName,
  'appTagline' => $appTagline,
])

</body>
</html>
