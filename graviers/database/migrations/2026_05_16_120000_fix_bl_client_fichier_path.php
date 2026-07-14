<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Correctif de données : les enregistrements bl_client créés avant le
 * 2026-05-16 ont leur fichier stocké au chemin 'temp_pdfs/...' alors que
 * le fichier réel a été déplacé vers 'lesBons/...'. Cette migration
 * normalise les chemins pour pointer vers la bonne destination.
 *
 * Voir ClientController.php (3 endroits BlClient::create corrigés).
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('bl_client')
            ->whereNotNull('fichier')
            ->where('fichier', 'like', 'temp_pdfs/%')
            ->get(['id', 'fichier']);

        foreach ($rows as $row) {
            $basename       = basename($row->fichier);
            $cheminCorrige  = 'lesBons/' . $basename;

            // Si le fichier est toujours dans temp_pdfs (jamais déplacé),
            // on tente le move ; sinon on suppose qu'il est déjà dans lesBons.
            if (Storage::disk('public')->exists($row->fichier)
                && !Storage::disk('public')->exists($cheminCorrige)) {
                Storage::disk('public')->move($row->fichier, $cheminCorrige);
            }

            DB::table('bl_client')
                ->where('id', $row->id)
                ->update(['fichier' => $cheminCorrige]);
        }
    }

    public function down(): void
    {
        // Pas de rollback : on ne va pas casser le chemin volontairement.
    }
};
