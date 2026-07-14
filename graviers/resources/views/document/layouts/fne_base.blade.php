@php
    $logoDALAKOUN = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('frontend/assets/imgs/logo/logoDALAKOUN.png')));
    $logoCI = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('frontend/assets/imgs/logo/logoci.png')));

    // Auto-génération du QR Code FNE si non fourni
    if (empty($fne_qr_code ?? '')) {
        try {
            $configQr = $fne_config ?? [];
            $qrParts = [
                'NCC:' . ($configQr['ncc'] ?? ''),
                'DOC:' . ($fne_numero ?? ''),
                'D:' . ($fne_date ?? now()->format('d/m/Y H:i:s')),
            ];
            $qrData = implode('|', $qrParts);
            $fne_qr_code = App\Services\FneService::genererQrCodeBase64($qrData);
        } catch (\Exception $e) {
            $fne_qr_code = '';
        }
    }
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>@yield('titre', 'Document FNE')</title>
    <style>
        @page {
            size: 210mm 297mm;
            margin: 12mm 35mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.4;
            padding: 0;
            margin: 0;
            background-color: #f5f5f5;
        }

        .fne-container {
            max-width: 210mm;
            margin: 20px auto;
            padding: 20mm;
            background: #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }

        @media print {
            body { background-color: #fff; }
            .fne-container { box-shadow: none; margin: 0; padding: 12mm 35mm; }
        }

        /* ========== EN-TÊTE ========== */
        .fne-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .fne-header td {
            vertical-align: top;
            padding: 0;
        }

        .encadre-emetteur {
            border: 1.5px solid #000;
            padding: 8px 12px;
            width: 50%;
            font-size: 9pt;
            line-height: 1.5;
        }

        .encadre-emetteur strong {
            font-size: 11pt;
        }

        .fne-numero-zone {
            text-align: right;
            width: 50%;
            padding-left: 15px;
        }

        .fne-numero-titre {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .fne-badges {
            text-align: right;
            margin-top: 5px;
        }

        .fne-badges-table {
            border-collapse: collapse;
            margin-left: auto;
        }

        .fne-badges-table td {
            vertical-align: top;
            padding: 0 5px;
        }

        .fne-qrcode img {
            width: 90px;
            height: 90px;
        }

        .fne-logo-dalakoun img {
            width: 80px;
            height: auto;
        }

        .fne-badge-ci {
            text-align: center;
        }

        .fne-badge-ci img {
            width: 70px;
            height: 70px;
            border: 2px solid #e67e22;
            border-radius: 50%;
            padding: 5px;
        }

        .fne-badge-ci-text {
            display: block;
            font-size: 7pt;
            font-weight: bold;
            color: #e67e22;
            text-align: center;
            margin-top: 3px;
            line-height: 1.2;
        }

        /* ========== INFOS ÉMETTEUR ========== */
        .fne-infos-emetteur {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .fne-infos-emetteur td {
            vertical-align: top;
            padding: 2px 0;
            font-size: 9pt;
        }

        .fne-infos-left {
            width: 55%;
            padding-right: 10px;
        }

        .fne-infos-left p {
            margin: 2px 0;
            font-size: 9pt;
        }

        /* ========== BLOC CLIENT ========== */
        .fne-client-bloc {
            border: 1px solid #ccc;
            padding: 8px 12px;
        }

        .fne-client-bloc p {
            margin: 2px 0;
            font-size: 9pt;
        }

        .fne-client-titre {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 3px;
        }

        /* ========== TABLEAU ARTICLES ========== */
        .fne-articles {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 5px;
        }

        .fne-articles th {
            background-color: #f0f0f0;
            border: 1px solid #999;
            padding: 5px 6px;
            font-size: 8.5pt;
            text-align: center;
            font-weight: bold;
        }

        .fne-articles td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 8.5pt;
        }

        .fne-articles .col-ref { width: 10%; }
        .fne-articles .col-designation { width: 30%; }
        .fne-articles .col-pu { width: 10%; text-align: right; }
        .fne-articles .col-qte { width: 6%; text-align: center; }
        .fne-articles .col-unite { width: 8%; text-align: center; }
        .fne-articles .col-taxes { width: 10%; text-align: center; }
        .fne-articles .col-rem { width: 8%; text-align: center; }
        .fne-articles .col-montant { width: 18%; text-align: right; }

        /* ========== TOTAUX ========== */
        .fne-totaux-outer {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .fne-totaux-outer td.fne-totaux-spacer {
            width: 55%;
            border: none;
        }

        .fne-totaux-outer td.fne-totaux-content {
            width: 45%;
            vertical-align: top;
            padding: 0;
        }

        .fne-totaux {
            width: 100%;
            border-collapse: collapse;
        }

        .fne-totaux td {
            border: 1px solid #999;
            padding: 4px 10px;
            font-size: 9pt;
        }

        .fne-totaux .label {
            text-align: right;
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .fne-totaux .valeur {
            text-align: right;
            width: 40%;
        }

        /* ========== RÉSUMÉ FISCAL ========== */
        .fne-resume-titre {
            font-weight: bold;
            font-size: 10pt;
            margin: 10px 0 4px 0;
        }

        .fne-resume {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .fne-resume th {
            background-color: #f0f0f0;
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 8.5pt;
            text-align: center;
            font-weight: bold;
        }

        .fne-resume td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-size: 8.5pt;
        }

        /* ========== PIED DE PAGE ========== */
        .fne-footer {
            border-top: 1px solid #000;
            padding-top: 8px;
            font-size: 8pt;
            text-align: center;
            color: #333;
            margin-top: 20px;
            line-height: 1.5;
        }

        /* ========== UTILITAIRES ========== */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .clear { clear: both; }

        @yield('styles')
    </style>
</head>
<body>
<div class="fne-container">

    {{-- ===== LOGO DALAKOUN EN HAUT ===== --}}
    <table style="width:100%; margin-bottom:8px;">
        <tr>
            <td style="text-align:right; vertical-align:middle;">
                <img src="{{ $logoDALAKOUN }}" alt="DALAKOUN" style="width:100px; height:auto;"><br>
                <span style="font-size:10pt; font-weight:bold;">@yield('type_document', 'Facture de vente') Nº {{ $fne_numero ?? '' }}</span>
                @if(!empty($fne_certified ?? false))
                    <br><span style="font-size:8pt; font-weight:bold; color:#0a8a3a;">CERTIFIÉE PAR LA DGI - FNE</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ===== EN-TÊTE FNE ===== --}}
    <table class="fne-header">
        <tr>
            <td>
                <div class="encadre-emetteur">
                    <strong>DALAKOUN</strong><br>
                    NCC : {{ $fne_config['ncc'] ?? '' }}<br>
                    Régime d'imposition : {{ $fne_config['regime_imposition'] ?? '' }}<br>
                    Centre des impôts : {{ $fne_config['centre_impots'] ?? '' }}
                </div>
            </td>
            <td class="fne-numero-zone">
                <div class="fne-badges">
                    <table class="fne-badges-table">
                        <tr>
                            @if(!empty($fne_qr_code))
                                <td class="fne-qrcode"><img src="{{ $fne_qr_code }}" alt="QR Code"></td>
                            @endif
                            <td class="fne-badge-ci">
                                <img src="{{ $logoCI }}" alt="CI">
                                <span class="fne-badge-ci-text">FACTURE NORMALISÉE<br>ÉLECTRONIQUE</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- ===== INFORMATIONS ÉMETTEUR + CLIENT ===== --}}
    <table class="fne-infos-emetteur">
        <tr>
            <td class="fne-infos-left">
                <p>RCCM : {{ $fne_config['rccm'] ?? '' }}</p>
                <p>Références bancaires : {{ $fne_config['ref_bancaires'] ?? '' }}</p>
                <p>Établissement : {{ $fne_config['nom_etablissement'] ?? '' }}</p>
                <p>Adresse : {{ $fne_config['adresse_siege'] ?? '' }}</p>
                <p>Nº Tel : {{ $fne_config['telephone'] ?? '' }}</p>
                <p>Mail : {{ $fne_config['email_entreprise'] ?? '' }}</p>
                @hasSection('vendeur')
                    <p>Nom du vendeur : @yield('vendeur')</p>
                @endif
                <p>Nom de PDV : {{ $fne_config['nom_pdv'] ?? '' }}</p>
                <p>Date et heure : {{ $fne_date ?? now()->format('d/m/Y H:i:s') }}</p>
                @hasSection('mode_paiement')
                    <p>Mode de paiement : @yield('mode_paiement')</p>
                @endif
                @hasSection('adresse_livraison')
                    <p>ADRESSE : @yield('adresse_livraison')</p>
                @endif
            </td>
            <td>
                <div class="fne-client-bloc">
                    <p class="fne-client-titre">Client</p>
                    <p>Nom : {{ $fne_client['nom'] ?? '' }}</p>
                    <p>Adresse : {{ $fne_client['adresse'] ?? '' }}</p>
                    <p>NCC : {{ $fne_client['ncc'] ?? '' }}</p>
                    <p>Régime d'imposition : {{ $fne_client['regime_imposition'] ?? '' }}</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="clear"></div>

    {{-- ===== TABLEAU DES ARTICLES ===== --}}
    @yield('articles')

    {{-- ===== TOTAUX ===== --}}
    @yield('totaux')

    {{-- ===== RÉSUMÉ DE LA FACTURE ===== --}}
    @yield('resume_fiscal')

    {{-- ===== PIED DE PAGE LÉGAL ===== --}}
    <div class="fne-footer">
        DALAKOUN,
        SARL au Capital de {{ $fne_config['capital_social'] ?? '' }}-RCCM
        N° {{ $fne_config['rccm'] ?? '' }},
        CC N°{{ $fne_config['ncc'] ?? '' }},
        CNPS N°{{ $fne_config['cnps'] ?? '' }}
        <br>
        Régime d'imposition {{ $fne_config['regime_imposition'] ?? '' }}
        <br>
        Centre des impôts {{ $fne_config['centre_impots'] ?? '' }}
    </div>

</div>{{-- fin .fne-container --}}

</body>
</html>
