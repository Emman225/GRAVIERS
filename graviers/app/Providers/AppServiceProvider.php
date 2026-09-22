<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        // \Carbon\Carbon::setLocale('fr');

        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Écritures comptables de trésorerie (lot 118, 21/09/2026) : posées sur les modèles, jamais bloquantes.
        // Ce fichier sert à CHAQUE page : un fichier du module absent du serveur (archive posée à moitié)
        // ne doit pas faire tomber le site — on se passe alors des écritures, la commande de reprise rattrapera.
        try {
            if (class_exists(\App\Services\Comptabilite\Declencheurs::class)) {
                \App\Services\Comptabilite\Declencheurs::brancher();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Déclencheurs comptables non branchés : ' . $e->getMessage());
        }
    }
}
