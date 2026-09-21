<?php

return [
    "userName" => "DALAKOUN SARL",
    "userMailSortant" =>  "test.reply@numerisk.net",
    "passwordMailSortant" => "6bnE5Uy6zvPu",
    "IMAP_Port" => 993,
    "POP3_Port" => 995,
    "SMTP_Port" => 465,

    "ServeurMailEntrant" => "smaak.o2switch.net",

    // Le site produit les PDF (reçus). L'API lui demande d'envoyer le reçu
    // d'un paiement confirmé ici, en présentant le jeton partagé
    // JETON_INTERNE (même valeur dans les deux .env).
    'url_site'      => env('URL_SITE', 'https://mongravier.com'),
    'email_contact' => env('EMAIL_CONTACT'),
    'jeton_interne' => env('JETON_INTERNE'),
    // Le stockage du site, où l'API dépose les fichiers des applications (17/09/2026).
    'chemin_stockage_site' => env('CHEMIN_STOCKAGE_SITE'),

];
