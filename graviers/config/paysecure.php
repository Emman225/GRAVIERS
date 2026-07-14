<?php

return [
    'url' => env('PAYSECURE_URL'),
    'api_key' => env('PAYSECURE_API_KEY'),
    'merchant_id' => env('PAYSECURE_MERCHANT_ID'),
    // Endpoint de consultation du statut d'un paiement (fourni par PaySecure).
    // Laisser vide tant qu'il n'est pas connu : la vérification automatique sera
    // alors désactivée et seule la confirmation manuelle admin sera utilisée.
    'status_url' => env('PAYSECURE_STATUS_URL'),
];
