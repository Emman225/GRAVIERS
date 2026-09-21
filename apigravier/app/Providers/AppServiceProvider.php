<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(255);

        // 12/09/2026 : les images du catalogue sont servies par le SITE ; son adresse
        // vient du .env (URL_SITE), plus jamais du code.
        \Help::$URL_BASE_FICHIER = rtrim((string) config('constantes.url_site'), '/') . '/storage/';
    }
}
