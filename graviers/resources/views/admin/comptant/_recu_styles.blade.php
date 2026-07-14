{{-- Variables : $isPdf (bool) --}}
<style>
    @if (!$isPdf)
    @media print {
        .d-print-none, .navbar-aside, .main-header, header.main-header, footer { display: none !important; }
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .content-main { padding: 0 !important; margin: 0 !important; }
        .recu-container { box-shadow: none !important; border: none !important; margin: 0 auto !important; }
    }
    .recu-container {
        max-width: 600px;
        margin: 30px auto;
        padding: 30px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border: 1px solid #e0e0e0;
        font-family: Arial, sans-serif;
    }
    @else
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #333; margin: 0; padding: 0; }
    .recu-container { padding: 8px 12px; }
    @endif

    .recu-header { border-bottom: 2px solid #1c57a3; padding-bottom: 8px; margin-bottom: 10px; }
    .recu-head-table { width: 100%; border-collapse: collapse; }
    .recu-head-logo { width: 90px; vertical-align: middle; padding-right: 10px; }
    .recu-head-logo img { max-width: 80px; max-height: 60px; display: block; }
    .recu-head-info { vertical-align: middle; }
    .recu-head-info h1 { color: #1c57a3; margin: 0 0 3px; font-size: 16px; letter-spacing: 0.5px; line-height: 1.1; }
    .recu-head-info .subtitle { color: #666; font-size: 9px; margin: 0; line-height: 1.4; }

    .recu-meta { display: table; width: 100%; margin: 6px 0; }
    .recu-meta-row { display: table-row; }
    .recu-meta-cell { display: table-cell; padding: 2px 0; vertical-align: top; }
    .recu-meta-label { color: #888; font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; }
    .recu-meta-value { font-weight: bold; color: #222; font-size: 11px; }

    .recu-numero { background: #1c57a3; color: white; padding: 6px 10px; text-align: center; font-size: 12px; font-weight: bold; letter-spacing: 0.8px; margin: 8px 0 6px; border-radius: 3px; }

    .recu-table { width: 100%; border-collapse: collapse; margin: 6px 0; }
    .recu-table td { padding: 3px 6px; border-bottom: 1px dashed #ccc; font-size: 10px; }
    .recu-table td:first-child { color: #666; width: 45%; }
    .recu-table td:last-child { text-align: right; font-weight: 600; color: #222; }

    .recu-montant { background: #e8f4ff; border: 1.5px solid #1c57a3; padding: 8px; text-align: center; margin: 8px 0; border-radius: 4px; }
    .recu-montant-label { color: #555; font-size: 9px; text-transform: uppercase; }
    .recu-montant-value { color: #1c57a3; font-size: 18px; font-weight: bold; margin: 2px 0; }

    .recu-tranche { background: #fff3cd; border-left: 3px solid #ffc107; padding: 5px 8px; margin: 6px 0; font-size: 9px; }

    .recu-resume { background: #f8f9fa; padding: 6px 8px; margin: 8px 0; border-radius: 3px; font-size: 9px; }
    .recu-resume-row { display: table; width: 100%; padding: 1px 0; }
    .recu-resume-label { display: table-cell; color: #666; }
    .recu-resume-value { display: table-cell; text-align: right; font-weight: bold; }
    .text-success { color: #28a745 !important; }
    .text-danger { color: #dc3545 !important; }

    .recu-barcode { text-align: center; margin: 8px 0 4px; }
    .recu-barcode-num { color: #555; font-size: 9px; letter-spacing: 1px; margin-top: 2px; font-family: 'Courier New', monospace; }

    .recu-signatures { display: table; width: 100%; margin-top: 12px; }
    .recu-sign { display: table-cell; width: 50%; text-align: center; padding-top: 18px; border-top: 1px solid #999; font-size: 8px; color: #666; }
    .recu-sign:first-child { padding-right: 8px; }
    .recu-sign:last-child { padding-left: 8px; }

    .recu-footer { text-align: center; margin-top: 8px; color: #999; font-size: 7px; border-top: 1px solid #eee; padding-top: 5px; }
</style>
