<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public_images' => [
            'driver' => 'local',
            'root' => public_path('images'),
            'url' => env('APP_URL') . '/images',
            'visibility' => 'public',
        ],

        // LES FICHIERS ENVOYÉS PAR LES APPLICATIONS (photo de profil, pièces,
        // bons de commande, preuves de virement, DFE / registre) doivent vivre
        // dans le STOCKAGE DU SITE : c'est lui que servent les adresses
        // https://mongravier.com/storage/… (Help::urlFichier) et que lit le
        // back-office. Ils tombaient dans le dossier de l'API, invisible du site
        // (photo de profil « qui ne marche pas », 17/09/2026). CHEMIN_STOCKAGE_SITE
        // (.env) = public_html/graviers/storage/app/public ; à vide, le dossier de
        // l'API (poste de développement).
        'principal' => [
            'driver' => 'local',
            'root' => env('CHEMIN_STOCKAGE_SITE') ?: storage_path('app/public'),
            'visibility' => 'public',
        ],

        'public' => [
            'driver' => 'local',
            'root' => env('CHEMIN_STOCKAGE_SITE') ?: storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('images') => storage_path('app/public/images'),
        public_path('storage') => storage_path('app/public'),
    ],

];
