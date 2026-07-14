{{-- Guide d'utilisation du tableau de bord Récap Dettes.
     Adapté de la feuille « 📖 Instructions » du fichier 06_RECAP_GLOBAL_DETTES.xlsx. --}}

<div class="recap-guide">
    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🎯 Objectif</h6>
        <p class="mb-0">
            Vue consolidée de toutes les <strong>dettes</strong> (sommes à payer) sur la plateforme GRAVIER.COM —
            fournisseurs de matériaux, livreurs et apporteurs d'affaires.
        </p>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">📊 Sources de données</h6>
        <ul class="recap-guide-list">
            <li>
                <strong>Module Dettes Fournisseurs</strong> — enlèvements en carrière et achats matériaux à régler.
                <a href="{{ route('show.fournisseurs.synthese') }}" class="recap-guide-link">→ Voir la synthèse</a>
            </li>
            <li>
                <strong>Module Dettes Livreurs</strong> — frais de livraison à reverser aux livreurs.
                <a href="{{ route('show.livreurs.synthese') }}" class="recap-guide-link">→ Voir la synthèse</a>
            </li>
            <li>
                <strong>Module Dettes Apporteurs</strong> — commissions à verser sur affaires apportées.
                <a href="{{ route('show.apporteurs.synthese') }}" class="recap-guide-link">→ Voir la synthèse</a>
            </li>
        </ul>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🔄 Actualisation des données</h6>
        <p class="mb-2">
            Les chiffres sont <strong>recalculés en temps réel</strong> à partir de la base de données.
            Aucune saisie manuelle ni import depuis Excel n'est nécessaire — différence majeure avec l'ancien fichier de récap.
        </p>
        <p class="mb-0 text-muted small">
            <i class="material-icons md-info" style="vertical-align:middle;font-size:16px;"></i>
            Pour forcer un recalcul après une nouvelle saisie de paiement, il suffit de recharger la page.
        </p>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">🎨 Code couleur des indicateurs</h6>
        <div class="recap-guide-colors">
            <span class="recap-guide-color-item"><span class="dot dot-blue"></span> Bleu — KPI principaux (totaux, indicateurs neutres)</span>
            <span class="recap-guide-color-item"><span class="dot dot-green"></span> Vert — Sain (paiements à jour)</span>
            <span class="recap-guide-color-item"><span class="dot dot-amber"></span> Orange — Avertissement (paiement partiel, à surveiller)</span>
            <span class="recap-guide-color-item"><span class="dot dot-red"></span> Rouge — Alerte (dette échue impayée, litige)</span>
            <span class="recap-guide-color-item"><span class="dot dot-yellow"></span> Jaune — Total / sous-total</span>
        </div>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">📋 Onglets disponibles</h6>
        <ul class="recap-guide-list">
            <li><strong>Tableau de bord</strong> — vue d'ensemble : total dû global, répartition par catégorie, Top créanciers.</li>
            <li><strong>Détail Fournisseurs</strong> — liste complète des dettes fournisseurs avec leurs statuts.</li>
            <li><strong>Détail Livreurs</strong> — liste complète des frais de livraison à régler.</li>
            <li><strong>Détail Apporteurs</strong> — liste complète des commissions à verser.</li>
        </ul>
    </section>

    <section class="recap-guide-section">
        <h6 class="recap-guide-title">✅ Bonnes pratiques</h6>
        <ol class="recap-guide-ol">
            <li><strong>Mise à jour hebdomadaire</strong> chaque vendredi (cycle de paiement standard livreurs).</li>
            <li><strong>Vérifier en priorité les dettes échues impayées</strong> avant d'engager les paiements.</li>
            <li><strong>Anticiper la trésorerie</strong> pour les 30 prochains jours en consultant les échéances.</li>
            <li><strong>Conserver une archive mensuelle</strong> via l'export PDF du tableau de bord.</li>
        </ol>
    </section>

    <section class="recap-guide-section recap-guide-contact">
        <h6 class="recap-guide-title">📞 Service Trésorerie GRAVIER.COM</h6>
        <p class="mb-1"><strong>Email :</strong> <a href="mailto:tresorerie@gravier.com">tresorerie@gravier.com</a></p>
        <p class="mb-0"><strong>Téléphone :</strong> +225 27 22 00 00 00</p>
    </section>
</div>
