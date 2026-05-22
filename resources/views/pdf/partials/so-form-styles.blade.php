{{-- Shared print styles: sales-order / formal invoice look (DomPDF-safe) --}}
<style>
    @page { margin: 14mm 16mm; }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 9px;
        color: #000;
        margin: 0;
        line-height: 1.35;
    }
    .muted { color: #333; }
    .upper { text-transform: uppercase; }
    .seller-name {
        font-size: 12px;
        font-weight: bold;
        margin: 0 0 4px;
        text-transform: uppercase;
    }
    .meta-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .meta-table td { padding: 2px 4px 2px 0; vertical-align: top; }
    .meta-label { width: 28%; font-weight: bold; white-space: nowrap; }
    .meta-val { width: 72%; }
    .box {
        border: 1px solid #000;
        padding: 6px 8px;
        margin-top: 8px;
    }
    .box-title {
        font-weight: bold;
        font-size: 8px;
        margin-bottom: 4px;
        border-bottom: 1px solid #000;
        padding-bottom: 2px;
        text-transform: uppercase;
    }
    .lines {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .lines th, .lines td {
        border: 1px solid #000;
        padding: 5px 4px;
        vertical-align: top;
    }
    .lines th {
        background: #e8e8e8;
        font-size: 8px;
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
    }
    .c { text-align: center; }
    .r { text-align: right; }
    .totals-wrap { width: 100%; margin-top: 10px; }
    .totals { border-collapse: collapse; margin-left: auto; width: 52%; }
    .totals td { border: 1px solid #000; padding: 5px 8px; }
    .totals .lab { font-weight: bold; background: #f3f3f3; }
    .words-box {
        border: 1px solid #000;
        padding: 6px 8px;
        margin-top: 8px;
        min-height: 28px;
    }
    .words-label { font-weight: bold; font-size: 8px; margin-bottom: 3px; }
    .bank-box {
        border: 1px solid #000;
        padding: 6px 8px;
        margin-top: 8px;
        font-size: 8px;
    }
    .terms { margin-top: 12px; font-size: 8px; line-height: 1.45; }
    .terms ol { margin: 4px 0 0 16px; padding: 0; }
    .terms li { margin-bottom: 3px; }
    .eoe { margin-top: 6px; font-weight: bold; font-size: 9px; }
    .sign-block { margin-top: 28px; width: 100%; }
    .sign-for { font-size: 8px; margin-bottom: 28px; }
    .sign-line {
        border-top: 1px solid #000;
        width: 240px;
        padding-top: 3px;
        font-size: 8px;
        font-weight: bold;
        text-align: center;
    }
    .water { font-size: 7px; color: #555; margin-top: 10px; }
</style>
