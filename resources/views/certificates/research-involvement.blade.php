<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Research involvement certificate - {{ $submission->reference_code }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; background: #e9edf0; color: #000; font: 12pt 'Times New Roman', Times, serif; }
        main { position: relative; isolation: isolate; max-width: 210mm; min-height: 297mm; margin: 32px auto; padding: 38.1mm 25.4mm 31.7mm; background: white; border: 1px solid #b9c5c8; }
        .stationery { position: absolute; z-index: -1; top: 0; left: 0; width: 100%; height: auto; }
        .page-layout { width: 100%; border-collapse: collapse; }
        .page-layout > thead, .page-layout > tfoot { display: none; }
        header { text-align: center; }
        h1 { font-size: 12pt; margin: 20px 0; text-align: center; }
        p { line-height: 1.65; }
        dl { margin: 18px 0; }
        dl div { padding: 5px 0; }
        dt { font-weight: bold; }
        dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        .extra { white-space: pre-wrap; }
        .signatures { display: flex; flex-wrap: wrap; gap: 48px; margin-top: 48px; }
        .signature { flex: 1 1 220px; border-top: 1px solid #172a37; text-align: center; padding-top: 10px; min-height: 65px; }
        .notice { margin-top: 32px; }
        .print-control { display: block; margin: 20px auto 0; padding: 10px 18px; border: 1px solid #172a37; background: white; color: #172a37; cursor: pointer; }
        @media (max-width: 600px) { main { margin: 0; padding: 20% 8% 15%; min-height: 100vh; } }
        @media print { body { background: white; } main { isolation: auto; border: 0; margin: 0; max-width: none; min-height: 0; padding: 0; background: transparent; } .stationery { position: fixed; top: 0; left: 0; width: 210mm; height: 297mm; z-index: -1; } .page-layout > thead { display: table-header-group; } .page-layout > tfoot { display: table-footer-group; } .page-layout > thead td { height: 38.1mm; } .page-layout > tfoot td { height: 31.7mm; } .page-layout > tbody > tr > td { padding: 0 25.4mm; vertical-align: top; } .print-control { display: none; } }
    </style>
</head>
<body>
<button type="button" class="print-control" id="print-certificate">Print certificate</button>
<main>
    <img class="stationery" src="data:image/png;base64,{{ $letterTemplate }}" alt="">
    <table class="page-layout"><thead><tr><td></td></tr></thead><tfoot><tr><td></td></tr></tfoot><tbody><tr><td>
    <header><strong>{{ $settings->institution_name }}</strong><br>{{ $settings->office_name }}</header>
    <h1>{{ $settings->heading }}</h1>
    <p>This certifies that the following research involvement of <strong>{{ $faculty->name }}</strong> has been institutionally approved.</p>
    <dl>
        <div><dt>Research title</dt><dd>{{ $submission->title }}</dd></div>
        <div><dt>Reference</dt><dd>{{ $submission->reference_code }}</dd></div>
        <div><dt>Faculty member</dt><dd>{{ $faculty->name }}</dd></div>
        <div><dt>Research type</dt><dd>{{ $submission->research_type_label }}</dd></div>
        <div><dt>Approval date</dt><dd>{{ $submission->approved_at?->format('F j, Y') ?? 'Not recorded' }}</dd></div>
        @if ($submission->indexing)
            <div><dt>Indexing (self-reported)</dt><dd>{{ $submission->indexing }}</dd></div>
        @endif
    </dl>
    @if ($settings->additional_text)<p class="extra">{{ $settings->additional_text }}</p>@endif
    <div class="signatures">
        <div class="signature">{{ $settings->first_signatory_name }}<br>{{ $settings->first_signatory_title }}</div>
        <div class="signature">{{ $settings->second_signatory_name }}<br>{{ $settings->second_signatory_title }}</div>
    </div>
    <p class="notice">This is an institutional approval record, not official Scopus verification. Any indexing information is self-reported and has not been independently verified by this certificate.</p>
    </td></tr></tbody></table>
</main>
<script nonce="{{ $nonce }}">document.getElementById('print-certificate').addEventListener('click', () => window.print());</script>
</body>
</html>