<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demande_compte_client_a_terme', function (Blueprint $table) {
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'documents_path')) {
                // JSON array de chemins de fichiers (RCCM, attestation revenus, bilan, etc.)
                $table->json('documents_path')->nullable()->after('description');
            }
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'plafond_credit')) {
                $table->decimal('plafond_credit', 18, 2)->nullable()->after('approuve');
            }
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'delai_paiement')) {
                // Délai en jours accordé par l'admin
                $table->integer('delai_paiement')->nullable()->after('plafond_credit');
            }
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'commentaire_admin')) {
                $table->text('commentaire_admin')->nullable()->after('delai_paiement');
            }
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'motif_refus')) {
                $table->text('motif_refus')->nullable()->after('commentaire_admin');
            }
            if (!Schema::hasColumn('demande_compte_client_a_terme', 'decided_at')) {
                $table->timestamp('decided_at')->nullable()->after('motif_refus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demande_compte_client_a_terme', function (Blueprint $table) {
            foreach (['documents_path', 'plafond_credit', 'delai_paiement', 'commentaire_admin', 'motif_refus', 'decided_at'] as $col) {
                if (Schema::hasColumn('demande_compte_client_a_terme', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
