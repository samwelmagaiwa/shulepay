* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
.section { padding: 34px 30px 20px; }
/* DomPDF's CSS3 selector support is incomplete (:not(:last-child) silently matched nothing in the
   bulk invoice print), so the page break is added per section from the Blade loop instead. */
.page-break { page-break-after: always; }
table { width: 100%; border-collapse: collapse; }
table.kv td { padding: 3px 4px; vertical-align: top; }
table.kv td.k { color: #555; width: 18%; }
table.kv td.v { font-weight: bold; width: 32%; }
table.grid th { font-size: 10px; letter-spacing: .3px; text-transform: uppercase; color: #444; background: #f1f4f2;
                border: 1px solid #999; padding: 5px 4px; text-align: center; }
table.grid td { border: 1px solid #999; padding: 4px 5px; text-align: center; }
table.summary td { background: #f7faf8; }
table.comments td { border: 1px solid #bbb; padding: 6px; }
table.sign td { width: 33%; padding-top: 10px; font-size: 10px; }
