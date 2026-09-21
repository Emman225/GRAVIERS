<?php

return [
    "userName" => "DALAKOUN SARL",
    // 12/09/2026 : adresse de contact du site (EMAIL_CONTACT du .env), voir Help::emailContact()
    'email_contact' => env('EMAIL_CONTACT'),
    "userMailSortant" =>  "test.reply@numerisk.net",
    "passwordMailSortant" => "6bnE5Uy6zvPu",
    "IMAP_Port" => 993,
    "POP3_Port" => 995,
    "SMTP_Port" => 465,
    "ServeurMailEntrant" => "smaak.o2switch.net",
    'type_user_id' => [
                        "SA" => 1,
                        "Admin" => 2,
                        "Gestionnaire" => 3,
                        "Client" => 4,
                        "Fournisseur" => 5,
                        "Apporteur" => 6,
                        "User_agent" => 7,
                        "Livreur" => 8,
                    ],
    // Le logo de la PLATEFORME, celui qui s'affiche sur le site et dans le
    // back-office.
    // 19/09/2026 : nouveau logo « Mon Gravier », sous un NOUVEAU nom de fichier — gardé sous l'ancien
    // nom (omer 1.png), les navigateurs et l'hébergeur continuaient de servir l'ancienne image en cache.
    // 19/09/2026 (soir) : le logo porte désormais « MONGRAVIER.COM » — de nouveau un nom de fichier neuf,
    // pour la même raison de cache. mon-gravier.png et omer 1.png portent aussi la nouvelle image.
    "logo" => 'frontend/assets/imgs/logo/mongravier-com.png',
    // Le logo de l'ENTREPRISE, réservé aux documents et aux courriels : reçus,
    // factures, devis, bons d'enlèvement. Fond blanc et sans transparence, parce
    // que dompdf compose mal un PNG à palette avec canal alpha.
    "logo_pdf" => 'frontend/assets/imgs/logo/dalakoun-blanc.png',

    // Jeton partagé avec l'API des mobiles (même valeur dans les deux .env) :
    // il autorise l'API à faire envoyer par le site le reçu d'un paiement.
    'jeton_interne' => env('JETON_INTERNE'),
];
