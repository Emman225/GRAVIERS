<?php

/**
 * ENVOI DE MESSAGES WHATSAPP.
 *
 * Un serveur ne peut pas écrire sur WhatsApp comme il envoie un courriel : il
 * faut un compte auprès d'un fournisseur, un numéro d'expédition validé et un
 * jeton d'accès. Tant que ces trois valeurs manquent, l'envoi est INACTIF et
 * rien ne change — le code de confirmation continue de partir par courriel.
 *
 * Fournisseur retenu : WhatsApp Cloud API de Meta, l'officiel. Il est documenté,
 * gratuit jusqu'à un millier de conversations par mois, et n'impose pas
 * d'intermédiaire. Si DALAKOUN passe par un autre fournisseur, seule la classe
 * d'envoi change — le reste de la chaîne est identique.
 *
 * À RENSEIGNER DANS LE FICHIER .env DU SERVEUR :
 *
 *   WHATSAPP_ACTIF=true
 *   WHATSAPP_JETON="EAAG..."            (jeton permanent de l'application Meta)
 *   WHATSAPP_NUMERO_ID="123456789"      (identifiant du numéro d'expédition)
 *   WHATSAPP_MODELE="code_confirmation" (nom du modèle approuvé par Meta)
 *
 * LE MODÈLE EST OBLIGATOIRE. Meta n'autorise un message à quelqu'un qui ne vous
 * a jamais écrit QUE s'il suit un modèle approuvé à l'avance. Un texte libre
 * serait refusé — c'est le piège classique de cette intégration.
 */
return [

    // Rien ne part tant que ce drapeau est faux. C'est aussi l'interrupteur
    // d'urgence si le fournisseur devient indisponible.
    'actif' => env('WHATSAPP_ACTIF', false),

    'jeton' => env('WHATSAPP_JETON'),

    'numero_id' => env('WHATSAPP_NUMERO_ID'),

    'url_base' => env('WHATSAPP_URL_BASE', 'https://graph.facebook.com/v21.0'),

    // Le modèle approuvé qui porte le code, et la langue dans laquelle il a été
    // approuvé. Un modèle approuvé en français ne s'envoie pas avec « en_US ».
    'modele' => env('WHATSAPP_MODELE', 'code_confirmation'),
    'langue' => env('WHATSAPP_LANGUE', 'fr'),

    // Indicatif ajouté aux numéros saisis sans lui. Les clients saisissent
    // « 0700000000 » ; WhatsApp attend « 2250700000000 ».
    'indicatif_defaut' => env('WHATSAPP_INDICATIF', '225'),

    // Au-delà, on n'attend plus : l'inscription ne doit pas rester suspendue à
    // un fournisseur lent. Le courriel, lui, est déjà parti.
    'delai' => env('WHATSAPP_DELAI', 8),
];
