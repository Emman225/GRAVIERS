<?php

/*
|--------------------------------------------------------------------------
| Configuration FNE (Facture Normalisée Electronique)
|--------------------------------------------------------------------------
|
| Configuration pour l'intégration de l'API FNE de la Direction Générale
| des Impôts de Côte d'Ivoire (DGI).
|
| Documentation officielle : "PROCEDURE D'INTERFACAGE DES ENTREPRISES PAR API"
| Plateforme test : http://54.247.95.108
| Endpoint test   : http://54.247.95.108/ws
| Endpoint prod   : transmis par la DGI après validation des spécimens
|
| Tant que `enabled=false` (ou que `api_key` est vide), aucun appel n'est
| effectué : la facture est créée localement sans certification FNE.
|
*/

return [

    // Active / désactive globalement la certification FNE.
    // Tant que la DGI n'a pas validé l'intégration et fourni la clé API,
    // laissez à false : la facture sera créée localement sans appel HTTP.
    'enabled' => env('FNE_ENABLED', false),

    // Endpoint de l'API FNE.
    //  - test : http://54.247.95.108/ws
    //  - prod : transmise par la DGI une fois l'intégration validée
    'base_url' => env('FNE_BASE_URL', 'http://54.247.95.108/ws'),

    // Clé API (Bearer Token) fournie par la DGI dans l'espace FNE de
    // l'entreprise (onglet "Paramétrage"), une fois l'intégration validée.
    'api_key' => env('FNE_API_KEY', ''),

    // Timeout (en secondes) des appels HTTP vers la plateforme FNE.
    'timeout' => (int) env('FNE_TIMEOUT', 20),

    // Nombre de tentatives en cas d'erreur réseau (5xx).
    'retry_times' => (int) env('FNE_RETRY_TIMES', 1),
    'retry_sleep' => (int) env('FNE_RETRY_SLEEP', 500), // ms

    // Valeurs par défaut transmises à l'API FNE
    'defaults' => [
        // Type de facturation (B2C, B2B, B2G, B2F)
        //  - B2C : client particulier
        //  - B2B : client professionnel possédant un NCC
        //  - B2G : client institution gouvernementale
        //  - B2F : client à l'international
        'template' => env('FNE_DEFAULT_TEMPLATE', 'B2C'),

        // Méthode de paiement (cash, card, check, mobile-money, transfer, deferred)
        'payment_method' => env('FNE_DEFAULT_PAYMENT_METHOD', 'cash'),

        // TVA appliquée par défaut sur les articles :
        //  - TVA  : 18% (taux normal)
        //  - TVAB : 9%  (taux réduit)
        //  - TVAC : 0%  (exonération conventionnelle)
        //  - TVAD : 0%  (exonération légale)
        'tax' => env('FNE_DEFAULT_TAX', 'TVA'),

        // Identifiants point de vente / établissement (configurés
        // côté FNE par la DGI lors de l'inscription de l'entreprise).
        'point_of_sale' => env('FNE_POINT_OF_SALE', 'PDV-01'),
        'establishment' => env('FNE_ESTABLISHMENT', 'GRAVIERS'),

        // Messages affichés sur la facture certifiée (facultatifs).
        'commercial_message' => env('FNE_COMMERCIAL_MESSAGE', ''),
        'footer' => env('FNE_FOOTER', ''),
    ],

    // Si true, on bloque la création de la facture lorsque l'API FNE
    // est indisponible. Si false (recommandé en attente des credentials),
    // la facture est créée en local et pourra être recertifiée plus tard.
    'block_on_failure' => env('FNE_BLOCK_ON_FAILURE', false),

    // Logs : canal Laravel utilisé pour les requêtes/réponses FNE.
    'log_channel' => env('FNE_LOG_CHANNEL', 'stack'),
];
