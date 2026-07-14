<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport de recette — Direction Générale</title>
<style>
    @page { margin: 18mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 18pt; color: #0a2540; margin: 0 0 4px 0; letter-spacing: -0.5px; }
    h2 { font-size: 13pt; color: #1c57a3; margin: 18px 0 6px 0; padding-bottom: 4px; border-bottom: 2px solid #1c57a3; }
    h3 { font-size: 11pt; color: #134380; margin: 12px 0 4px 0; }
    p { margin: 4px 0; }
    .cover { text-align: center; margin-bottom: 12px; padding: 16px; border: 2px solid #1c57a3; border-radius: 8px; background: #f5f7fb; }
    .cover h1 { color: #0a2540; }
    .cover .meta { color: #6b7280; font-size: 9.5pt; margin-top: 8px; }
    .badge-ok { display: inline-block; padding: 2px 8px; background: #d1fae5; color: #065f46; border-radius: 4px; font-size: 9pt; font-weight: 600; }
    .badge-pending { display: inline-block; padding: 2px 8px; background: #fef3c7; color: #92400e; border-radius: 4px; font-size: 9pt; font-weight: 600; }
    .badge-ko { display: inline-block; padding: 2px 8px; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 9pt; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0 12px 0; font-size: 10pt; }
    th { background: #1c57a3; color: #fff; padding: 6px 8px; text-align: left; }
    td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }
    .alert-info { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 8px 12px; margin: 8px 0; color: #1e3a8a; }
    .alert-warn { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 8px 12px; margin: 8px 0; color: #78350f; }
    .alert-success { background: #ecfdf5; border-left: 4px solid #10b981; padding: 8px 12px; margin: 8px 0; color: #065f46; }
    ol li, ul li { margin-bottom: 3px; }
    .step { background: #f9fafb; border-left: 3px solid #1c57a3; padding: 8px 12px; margin: 6px 0; border-radius: 0 6px 6px 0; }
    .step strong { color: #0a2540; }
    .small { font-size: 9pt; color: #6b7280; }
    code { background: #f3f4f6; padding: 1px 5px; border-radius: 3px; font-size: 9pt; color: #1f2937; }
</style>
</head>
<body>

<div class="cover">
    <h1>GRAVIER.COM — Rapport de recette</h1>
    <p style="font-size: 12pt; color: #134380; margin-top: 6px;">
        Synthèse des évolutions livrées et plan de tests pour la Direction Générale
    </p>
    <div class="meta">
        Document destiné à : <strong>Monsieur le Directeur Général</strong><br>
        Date : {{ \Carbon\Carbon::now()->locale('fr')->translatedFormat('d F Y') }}<br>
        Périmètre : 7 modules de gestion documentaire (Excel) + refonte UI + paramétrage centralisé
    </div>
</div>

{{-- ============================================================
     1. RÉSUMÉ EXÉCUTIF
     ============================================================ --}}
<h2>1. Résumé exécutif</h2>

<p>
    L'application GRAVIER.COM a été enrichie pour intégrer <strong>7 modules métier</strong> issus de fichiers Excel
    fournis par la direction. Chaque module dispose désormais de ses écrans dédiés, accessibles via le menu de gauche,
    avec sauvegarde en base de données (plus aucun export manuel Excel nécessaire).
</p>
<p>
    La <strong>charte graphique</strong> de l'interface d'administration et des écrans de connexion a été modernisée :
    tableaux de bord premium, boutons d'action homogènes, confirmations de suppression sécurisées, et pages de connexion
    distinctes par profil (administration, client, livreur, apporteur, fournisseur).
</p>
<p>
    Une <strong>page de paramétrage centralisée</strong> (<code>Paramètres</code> dans le menu) permet aux gestionnaires
    d'ajuster les délais, taux et fréquences sans avoir besoin du développeur.
</p>

<div class="alert-success">
    <strong>État global :</strong>
    <span class="badge-ok">Code livré et testé techniquement (compile + lint + tests à blanc OK)</span>
    — La recette utilisateur clic-par-clic reste à effectuer (cf. plan de tests page 4).
</div>

{{-- ============================================================
     2. CE QUI A ÉTÉ LIVRÉ
     ============================================================ --}}
<h2>2. Ce qui a été livré</h2>

<h3>2.1 Sept modules métier (issus des fichiers Excel)</h3>
<table>
    <tr><th>Module</th><th>Écrans disponibles</th><th>État</th></tr>
    <tr>
        <td>Créances clients à terme</td>
        <td>Clients · Factures · Paiements · Relances · Synthèse</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Créances clients comptant (paiement en agence)</td>
        <td>Commandes comptant · Encaissements agence · Synthèse</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Dettes fournisseurs</td>
        <td>Fournisseurs · Enlèvements · Paiements · Synthèse</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Dettes livreurs</td>
        <td>Livreurs · Livraisons · Paiements · Synthèse</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Dettes apporteurs d'affaires</td>
        <td>Apporteurs · Commissions · Paiements · Synthèse</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Récap global des dettes</td>
        <td>Tableau de bord consolidé (fournisseurs + livreurs + apporteurs)</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
    <tr>
        <td>Récap global des créances</td>
        <td>Tableau de bord consolidé (terme + comptant)</td>
        <td><span class="badge-ok">Livré</span></td>
    </tr>
</table>

<h3>2.2 Page « Paramètres » centralisée</h3>
<p>
    Accessible via le menu <strong>Paramètres</strong>, elle regroupe désormais tous les réglages de l'application sous forme d'onglets :
</p>
<table>
    <tr><th>Onglet</th><th>Réglages disponibles</th></tr>
    <tr><td>Configuration générale</td><td>Devise, montant par point de fidélité</td></tr>
    <tr><td>Livraison &amp; TVA</td><td>Taux TVA, prix par km, coût livraison minimum</td></tr>
    <tr><td>Gestionnaires &amp; Notifications</td><td>Email trésorier, email directeur marketing, gestionnaires validants</td></tr>
    <tr><td>Prix personnalisés</td><td>Tarifs négociés par client / produit</td></tr>
    <tr><td><strong>Créances à terme</strong> (nouveau)</td><td>Délai de relance standard, seuil alerte retard</td></tr>
    <tr><td><strong>Comptant / Agence</strong> (nouveau)</td><td>Délai max paiement agence, délai annulation auto</td></tr>
    <tr><td><strong>Livreurs</strong> (nouveau)</td><td>Fréquence de paiement (Quotidien / Hebdomadaire / Bimensuel / Mensuel), Jour de paiement</td></tr>
    <tr><td><strong>Apporteurs</strong> (nouveau)</td><td>Taux de commission standard, délai de paiement commission</td></tr>
</table>

<div class="alert-info">
    <strong>Information importante :</strong> les valeurs configurées dans ces 4 nouveaux onglets sont <strong>réellement utilisées</strong>
    par l'application — un changement modifie le comportement effectif (relances, annulations automatiques, calculs de commission, alertes).
</div>

<h3>2.3 Refonte de l'interface utilisateur</h3>
<ul>
    <li><strong>Tableau de bord du gestionnaire</strong> : présentation moderne avec indicateurs visuels (KPI), graphiques, accès rapides.</li>
    <li><strong>Tables de données</strong> (DataTables) : design uniforme premium, recherche, tri, export Excel/PDF, pagination cohérente sur tous les écrans.</li>
    <li><strong>Boutons de suppression</strong> : confirmation systématique via boîte de dialogue moderne (SweetAlert2) — protection contre les suppressions accidentelles.</li>
    <li><strong>Pages de connexion</strong> : design distinctif par profil utilisateur :
        <ul>
            <li>Administration / Gestionnaire — palette bleu marine</li>
            <li>Client (e-commerce) — palette orange / coucher de soleil</li>
            <li>Livreur — palette verte</li>
            <li>Apporteur — palette ambrée</li>
            <li>Fournisseur — palette violette</li>
            <li>Réinitialisation de mot de passe — palette rose</li>
        </ul>
    </li>
    <li><strong>Espace client</strong> (<code>/mon-compte</code>) : redirection automatique après connexion réussie.</li>
</ul>

<h3>2.4 Automatisations métier</h3>
<table>
    <tr><th>Automatisation</th><th>Fonctionnement</th></tr>
    <tr>
        <td>Annulation automatique des commandes comptant</td>
        <td>Une commande comptant non payée passé le délai configuré (par défaut 7 jours) est automatiquement annulée chaque nuit.</td>
    </tr>
    <tr>
        <td>Relance des clients à terme</td>
        <td>L'écran <strong>Relances</strong> affiche en haut la liste « À relancer aujourd'hui » avec un bouton qui pré-remplit le formulaire de relance (niveau calculé selon le retard).</td>
    </tr>
    <tr>
        <td>Calcul des commissions apporteurs</td>
        <td>Si l'apporteur n'a pas de taux personnalisé, le taux standard configuré est appliqué automatiquement.</td>
    </tr>
    <tr>
        <td>Cycle de paiement des livreurs</td>
        <td>L'écran <strong>Paiements livreurs</strong> affiche la prochaine date de paiement (vert si aujourd'hui, orange si imminent, bleu sinon) selon la fréquence configurée.</td>
    </tr>
    <tr>
        <td>Alerte rouge sur factures</td>
        <td>Les factures dont le retard dépasse le seuil configuré apparaissent en rouge dans la liste.</td>
    </tr>
</table>

{{-- ============================================================
     3. CE QUI RESTE À FAIRE
     ============================================================ --}}
<h2>3. Ce qui reste à faire (transparent)</h2>

<table>
    <tr><th>Sujet</th><th>Description</th><th>Priorité</th></tr>
    <tr>
        <td>Cron serveur LWS</td>
        <td>Activer la planification quotidienne (commande <code>php artisan schedule:run</code> chaque minute) pour que l'annulation automatique des commandes périmées s'exécute. Pour le moment, elle peut être lancée à la main.</td>
        <td><span class="badge-pending">À planifier avec l'hébergeur</span></td>
    </tr>
    <tr>
        <td>Listes de référence éditables (Chantier B)</td>
        <td>Permettre la modification des statuts métier (À échoir, Soldée, etc.), des types de véhicules livreurs (Tricycle, Camion 5T…) et des Mobile Money (Orange, MTN, Moov, Wave) directement depuis l'admin.</td>
        <td><span class="badge-pending">Phase 2</span></td>
    </tr>
    <tr>
        <td>Guide d'utilisation des récaps (Chantier C)</td>
        <td>Intégrer le contenu des feuilles « 📖 Instructions » des fichiers Récap dette / Récap créances en panneau d'aide contextuel sur les deux tableaux de bord récapitulatifs.</td>
        <td><span class="badge-pending">Phase 2</span></td>
    </tr>
    <tr>
        <td>Recette utilisateur (UAT)</td>
        <td>Le code a été testé techniquement. Une session de tests utilisateur (cf. plan ci-dessous) reste à organiser pour valider les parcours métier en conditions réelles.</td>
        <td><span class="badge-ko">À planifier maintenant</span></td>
    </tr>
</table>

{{-- ============================================================
     4. PLAN DE TESTS UTILISATEUR
     ============================================================ --}}
<h2>4. Plan de tests utilisateur (à dérouler par le DG)</h2>

<p class="small">
    Pour chaque test : se connecter avec un compte Administrateur, suivre les étapes, et valider que le résultat correspond
    à la description « Résultat attendu ». En cas d'écart, noter le numéro du test et l'écran concerné.
</p>

<h3>Test 1 — Pages de connexion distinctes</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Ouvrir <code>/login-account</code> dans le navigateur</li>
        <li>Ouvrir <code>/client/login</code> dans un autre onglet</li>
        <li>Comparer visuellement les deux pages</li>
    </ol>
    <strong>Résultat attendu :</strong> les deux pages doivent être visuellement différentes (bleu marine pour l'admin, orange chaud pour le client).
</div>

<h3>Test 2 — Modification d'un paramètre métier</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Aller dans <strong>Paramètres → Apporteurs</strong></li>
        <li>Modifier le « Taux de commission standard » de 3 à 5 %</li>
        <li>Cliquer sur « Appliquer les changements »</li>
        <li>Vérifier le message de confirmation</li>
        <li>Recharger la page et vérifier que la valeur 5 est conservée</li>
    </ol>
    <strong>Résultat attendu :</strong> la nouvelle valeur est sauvegardée et persiste.
</div>

<h3>Test 3 — Création d'une agence</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Aller dans le menu <strong>Configuration → Agences</strong> (ou via le lien depuis l'onglet Comptant des Paramètres)</li>
        <li>Cliquer sur « Nouvelle agence »</li>
        <li>Renseigner : Code AG-TEST, Nom Agence Test, Adresse Cocody</li>
        <li>Enregistrer</li>
    </ol>
    <strong>Résultat attendu :</strong> l'agence apparaît dans la liste et peut être désactivée / réactivée / modifiée.
</div>

<h3>Test 4 — Visualisation d'une créance à terme</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Menu <strong>Client → Client à terme → Factures</strong></li>
        <li>Vérifier que les colonnes correspondent au fichier Excel d'origine</li>
        <li>Identifier une facture en retard : la ligne doit apparaître en rouge si le retard dépasse le seuil paramétré (15 jours par défaut)</li>
    </ol>
    <strong>Résultat attendu :</strong> données cohérentes et alerte rouge fonctionnelle.
</div>

<h3>Test 5 — Section « À relancer aujourd'hui »</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Menu <strong>Client → Client à terme → Relances</strong></li>
        <li>Vérifier la présence en haut de page d'un encart jaune « À relancer aujourd'hui »</li>
        <li>Cliquer sur le bouton « Relancer » d'une ligne</li>
        <li>Vérifier que le formulaire de relance s'ouvre déjà pré-rempli avec le bon client, la bonne facture et un niveau cohérent</li>
        <li>Compléter et enregistrer la relance</li>
    </ol>
    <strong>Résultat attendu :</strong> la relance est sauvegardée et apparaît dans la liste principale.
</div>

<h3>Test 6 — Enregistrement d'un paiement en agence</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Menu <strong>Client → Client comptant → Encaissements agence</strong></li>
        <li>Cliquer sur « Enregistrer un paiement »</li>
        <li>Choisir une commande, saisir le montant, le mode de paiement, l'agence</li>
        <li>Enregistrer</li>
        <li>Vérifier que le reçu PDF est généré et téléchargeable</li>
    </ol>
    <strong>Résultat attendu :</strong> paiement sauvegardé, reçu PDF correct et imprimable.
</div>

<h3>Test 7 — Cycle de paiement livreur</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Menu <strong>Livreur → Paiements</strong></li>
        <li>Vérifier la présence en haut d'un bandeau « Prochain cycle de paiement » avec une date</li>
        <li>Aller dans <strong>Paramètres → Livreurs</strong>, changer la fréquence en « Quotidien »</li>
        <li>Revenir sur l'écran Paiements livreurs</li>
    </ol>
    <strong>Résultat attendu :</strong> le bandeau affiche désormais « Aujourd'hui » en vert.
</div>

<h3>Test 8 — Confirmation de suppression sécurisée</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Sur n'importe quel écran de liste, cliquer sur le bouton « Supprimer » (icône poubelle)</li>
        <li>Vérifier qu'une boîte de dialogue moderne demande confirmation</li>
        <li>Cliquer sur « Annuler » : rien ne doit être supprimé</li>
        <li>Recommencer et confirmer : la suppression doit s'effectuer</li>
    </ol>
    <strong>Résultat attendu :</strong> aucune suppression accidentelle possible.
</div>

<h3>Test 9 — Tableau de bord premium</h3>
<div class="step">
    <strong>Étapes :</strong>
    <ol>
        <li>Aller sur l'accueil gestionnaire (<code>/gestionnaire/home</code>)</li>
        <li>Vérifier la présence d'indicateurs (KPI) avec dégradés colorés et icônes</li>
        <li>Aller sur <strong>Récap créances → Tableau de bord</strong> et <strong>Récap dettes → Tableau de bord</strong></li>
        <li>Vérifier la même cohérence visuelle</li>
    </ol>
    <strong>Résultat attendu :</strong> trois tableaux de bord modernes, lisibles et cohérents entre eux.
</div>

{{-- ============================================================
     5. CONCLUSION
     ============================================================ --}}
<h2>5. Conclusion</h2>

<p>
    Le périmètre demandé sur les <strong>7 modules métier</strong> issus des fichiers Excel est livré et opérationnel.
    Les paramètres de gestion sont désormais accessibles à un gestionnaire non-développeur via une interface dédiée,
    et leur impact métier est réel (et non plus simplement informatif).
</p>
<p>
    Deux chantiers complémentaires (listes de référence éditables et guide d'utilisation des récaps) restent à programmer
    en phase 2 en fonction de la priorité que vous leur accorderez.
</p>
<p>
    Avant la mise en production officielle, il est <strong>indispensable</strong> de :
</p>
<ol>
    <li>Faire dérouler le plan de tests ci-dessus par un utilisateur métier (DG ou gestionnaire désigné).</li>
    <li>Activer la planification automatique sur le serveur LWS (à confirmer avec le développeur).</li>
    <li>Valider la complétude des données existantes dans les nouvelles tables (agences, paiements fournisseurs, livreurs, apporteurs).</li>
</ol>

<p style="margin-top: 18px; font-size: 9pt; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px;">
    Document généré automatiquement le {{ \Carbon\Carbon::now()->locale('fr')->translatedFormat('d/m/Y à H:i') }} —
    GRAVIER.COM | Direction des Systèmes d'Information
</p>

</body>
</html>
