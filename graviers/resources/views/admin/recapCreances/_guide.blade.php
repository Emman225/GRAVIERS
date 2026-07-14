{{-- Guide d'utilisation du tableau de bord Récap Créances.
     Adapté de la feuille « 📖 Instructions » du fichier 07_RECAP_GLOBAL_CREANCES.xlsx. --}}

<div class="recap-guide">
    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🎯 Objectif</h6>
        <p class="mb-0">
            Vue consolidée de toutes les <strong>créances clients</strong> (sommes à encaisser) sur la plateforme GRAVIER.COM —
            tous canaux confondus : factures à terme et commandes payables en agence.
        </p>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">📊 Sources de données</h6>
        <ul class="recap-guide-list">
            <li>
                <strong>Module Créances Clients à Terme</strong> — factures émises avec date d'échéance contractuelle.
                <a href="{{ route('show.creancesTerme.synthese') }}" class="recap-guide-link">→ Voir la synthèse</a>
            </li>
            <li>
                <strong>Module Comptant (paiement en agence)</strong> — commandes à régler dans un délai court (3 jours par défaut).
                <a href="{{ route('show.comptant.synthese') }}" class="recap-guide-link">→ Voir la synthèse</a>
            </li>
        </ul>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🔄 Actualisation des données</h6>
        <p class="mb-2">
            Les chiffres sont <strong>recalculés en temps réel</strong> à partir de la base de données à chaque ouverture
            du tableau de bord. Aucune saisie manuelle n'est nécessaire (différence majeure avec l'ancien fichier Excel).
        </p>
        <p class="mb-0 text-muted small">
            <i class="material-icons md-info" style="vertical-align:middle;font-size:16px;"></i>
            Pour forcer un recalcul après une saisie de paiement, il suffit de recharger la page.
        </p>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🎨 Code couleur des indicateurs</h6>
        <div class="recap-guide-colors">
            <span class="recap-guide-color-item"><span class="dot dot-blue"></span> Bleu — KPI principaux (totaux, indicateurs neutres)</span>
            <span class="recap-guide-color-item"><span class="dot dot-green"></span> Vert — Sain (recouvrement OK, à échoir non urgent)</span>
            <span class="recap-guide-color-item"><span class="dot dot-amber"></span> Orange — Avertissement (retard modéré, à surveiller)</span>
            <span class="recap-guide-color-item"><span class="dot dot-red"></span> Rouge — Alerte (échue impayée, retard critique)</span>
            <span class="recap-guide-color-item"><span class="dot dot-yellow"></span> Jaune — Total / sous-total</span>
        </div>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">📋 Onglets disponibles</h6>
        <ul class="recap-guide-list">
            <li><strong>Tableau de bord</strong> — vue d'ensemble : KPI, répartition, Top 5 débiteurs, créances échues prioritaires.</li>
            <li><strong>Détail Terme</strong> — liste complète des factures à terme avec leurs statuts.</li>
            <li><strong>Détail Comptant</strong> — liste complète des commandes comptant avec leurs encaissements.</li>
        </ul>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">⚡ Actions recommandées</h6>
        <div class="recap-guide-actions">
            <div class="recap-guide-action recap-guide-action-daily">
                <div class="recap-guide-action-tag">Quotidien</div>
                <p>Vérifier la section <strong>« Créances échues impayées »</strong> du tableau de bord.</p>
            </div>
            <div class="recap-guide-action recap-guide-action-weekly">
                <div class="recap-guide-action-tag">Hebdomadaire</div>
                <p>Mettre à jour les paiements reçus dans les modules <em>Créances Terme</em> et <em>Comptant</em>.</p>
            </div>
            <div class="recap-guide-action recap-guide-action-monthly">
                <div class="recap-guide-action-tag">Mensuel</div>
                <p>Faire un point sur les <strong>Top débiteurs</strong> et engager des relances ciblées via le module Relances.</p>
            </div>
        </div>
    </section>

    <section class="recap-guide-section recap-guide-contact">
        <h6 class="recap-guide-title">📞 Service Recouvrement GRAVIER.COM</h6>
        <p class="mb-1"><strong>Email :</strong> <a href="mailto:recouvrement@gravier.com">recouvrement@gravier.com</a></p>
        <p class="mb-0"><strong>Téléphone :</strong> +225 27 22 00 00 00</p>
    </section>
</div>
