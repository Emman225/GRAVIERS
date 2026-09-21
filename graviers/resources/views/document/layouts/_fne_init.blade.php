@php
    $config = $config ?? App\Models\Configuration::first();

    $fne_config = [
        'ncc' => $config->ncc ?? '',
        'regime_imposition' => $config->regime_imposition ?? '',
        'centre_impots' => $config->centre_impots ?? '',
        'rccm' => $config->rccm ?? '',
        'ref_bancaires' => $config->ref_bancaires ?? '',
        'nom_etablissement' => $config->nom_etablissement ?? '',
        'adresse_siege' => $config->adresse_siege ?? '',
        'telephone' => $config->telephone ?? '',
        'email_entreprise' => $config->email_entreprise ?? '',
        'nom_pdv' => $config->nom_pdv ?? '',
        'capital_social' => $config->capital_social ?? '',
        'cnps' => $config->cnps ?? '',
    ];

    $fne_date = $fne_date ?? now()->format('d/m/Y H:i:s');

    // Client info - adaptable selon contexte
    if (!isset($fne_client)) {
        $clientObj = $client ?? null;
        $fne_client = [
            'nom' => ($clientObj ? ucfirst($clientObj->display_name ?? '') : ''),
            'adresse' => $fne_adresse ?? '',
            // Le régime d'imposition est porté par la fiche client, comme le NCC.
            'ncc' => ($clientObj->ncc_clt ?? ''),
            'regime_imposition' => App\Support\RegimeImposition::libelle($clientObj->regime_imposition ?? ''),
        ];
    }

    // Quand le document connaît l'adresse de SA commande, elle prime sur
    // l'adresse par défaut du client : c'est celle du bon de livraison.
    if (!empty($fne_adresse)) {
        $fne_client['adresse'] = $fne_adresse;
    }

    // Génération du QR Code FNE
    if (!isset($fne_qr_code) || $fne_qr_code === null) {
        $fne_qr_code = '';
        try {
            $qrParts = [
                'NCC:' . ($config->ncc ?? ''),
                'DOC:' . ($fne_numero ?? ''),
                'D:' . $fne_date,
            ];

            // Ajouter le montant si disponible
            if (isset($fne_montant_ttc)) {
                $qrParts[] = 'TTC:' . number_format($fne_montant_ttc, 0, '', '') . 'FCFA';
            }

            $qrData = implode('|', $qrParts);
            $fne_qr_code = App\Services\FneService::genererQrCodeBase64($qrData);
        } catch (\Exception $e) {
            $fne_qr_code = '';
        }
    }
@endphp
