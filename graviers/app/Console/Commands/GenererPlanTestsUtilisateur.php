<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use Carbon\Carbon;

class GenererPlanTestsUtilisateur extends Command
{
    protected $signature = 'gravier:plan-tests-utilisateur
        {--out= : Chemin de sortie. Défaut : storage/app/public/plan-tests-utilisateur.docx}';

    protected $description = 'Génère un plan de tests utilisateur Word (.docx) sur 3 jours pour la recette complète de GRAVIER.COM.';

    public function handle(): int
    {
        $out = $this->option('out') ?: storage_path('app/public/plan-tests-utilisateur.docx');
        $dir = dirname($out);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $documentXml = $this->buildDocumentXml();
        $stylesXml   = $this->buildStylesXml();
        $rels        = $this->buildRels();
        $docRels     = $this->buildDocRels();
        $contentTypes = $this->buildContentTypes();

        if (file_exists($out)) @unlink($out);

        $zip = new ZipArchive();
        if ($zip->open($out, ZipArchive::CREATE) !== true) {
            $this->error("Impossible d'ouvrir le fichier de sortie : $out");
            return self::FAILURE;
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/_rels/document.xml.rels', $docRels);
        $zip->addFromString('word/styles.xml', $stylesXml);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $size = round(filesize($out) / 1024, 1);
        $this->info("Plan de tests généré : {$out} ({$size} Ko)");
        return self::SUCCESS;
    }

    private function buildContentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;
    }

    private function buildRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
    }

    private function buildDocRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function buildStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:docDefaults>
        <w:rPrDefault>
            <w:rPr>
                <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
                <w:sz w:val="22"/>
                <w:szCs w:val="22"/>
                <w:lang w:val="fr-FR"/>
            </w:rPr>
        </w:rPrDefault>
        <w:pPrDefault>
            <w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr>
        </w:pPrDefault>
    </w:docDefaults>
    <w:style w:type="paragraph" w:styleId="Title">
        <w:name w:val="Title"/>
        <w:pPr><w:spacing w:before="240" w:after="240"/><w:jc w:val="center"/></w:pPr>
        <w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="44"/><w:color w:val="0A2540"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading1">
        <w:name w:val="heading 1"/>
        <w:pPr><w:spacing w:before="360" w:after="120"/><w:pBdr><w:bottom w:val="single" w:sz="12" w:color="1C57A3"/></w:pBdr></w:pPr>
        <w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1C57A3"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading2">
        <w:name w:val="heading 2"/>
        <w:pPr><w:spacing w:before="240" w:after="100"/></w:pPr>
        <w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="134380"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading3">
        <w:name w:val="heading 3"/>
        <w:pPr><w:spacing w:before="180" w:after="80"/></w:pPr>
        <w:rPr><w:b/><w:sz w:val="22"/><w:color w:val="0A2540"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Quote">
        <w:name w:val="Quote"/>
        <w:pPr><w:ind w:left="360"/><w:shd w:val="clear" w:color="auto" w:fill="EFF6FF"/></w:pPr>
        <w:rPr><w:i/><w:color w:val="1E3A8A"/></w:rPr>
    </w:style>
</w:styles>
XML;
    }

    private function xml(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function p(string $text, string $style = '', bool $bold = false, string $color = ''): string
    {
        $pPr = $style ? "<w:pPr><w:pStyle w:val=\"{$style}\"/></w:pPr>" : '';
        $rPr = '';
        if ($bold || $color) {
            $rPr .= '<w:rPr>';
            if ($bold) $rPr .= '<w:b/>';
            if ($color) $rPr .= '<w:color w:val="' . $color . '"/>';
            $rPr .= '</w:rPr>';
        }
        return "<w:p>{$pPr}<w:r>{$rPr}<w:t xml:space=\"preserve\">" . $this->xml($text) . "</w:t></w:r></w:p>";
    }

    private function pMixed(array $runs, string $style = ''): string
    {
        $pPr = $style ? "<w:pPr><w:pStyle w:val=\"{$style}\"/></w:pPr>" : '';
        $body = '';
        foreach ($runs as $r) {
            $rPr = '';
            if (!empty($r['bold']) || !empty($r['color']) || !empty($r['italic'])) {
                $rPr .= '<w:rPr>';
                if (!empty($r['bold']))   $rPr .= '<w:b/>';
                if (!empty($r['italic'])) $rPr .= '<w:i/>';
                if (!empty($r['color']))  $rPr .= '<w:color w:val="' . $r['color'] . '"/>';
                $rPr .= '</w:rPr>';
            }
            $body .= "<w:r>{$rPr}<w:t xml:space=\"preserve\">" . $this->xml($r['text']) . "</w:t></w:r>";
        }
        return "<w:p>{$pPr}{$body}</w:p>";
    }

    private function bullet(string $text, int $indent = 360): string
    {
        $pPr = "<w:pPr><w:ind w:left=\"{$indent}\" w:hanging=\"180\"/></w:pPr>";
        $body = "<w:r><w:t xml:space=\"preserve\">•  " . $this->xml($text) . "</w:t></w:r>";
        return "<w:p>{$pPr}{$body}</w:p>";
    }

    private function step(int $num, string $text): string
    {
        $pPr = "<w:pPr><w:ind w:left=\"360\" w:hanging=\"180\"/></w:pPr>";
        $body = "<w:r><w:rPr><w:b/></w:rPr><w:t xml:space=\"preserve\">{$num}.  </w:t></w:r><w:r><w:t xml:space=\"preserve\">" . $this->xml($text) . "</w:t></w:r>";
        return "<w:p>{$pPr}{$body}</w:p>";
    }

    private function testBlock(string $id, string $title, array $steps, string $expected): string
    {
        $body  = $this->pMixed([
            ['text' => "☐  {$id} — ", 'bold' => true, 'color' => '0A2540'],
            ['text' => $title, 'bold' => true],
        ]);
        $body .= $this->p('Étapes :', '', true);
        foreach ($steps as $i => $s) {
            $body .= $this->step($i + 1, $s);
        }
        $body .= $this->pMixed([
            ['text' => 'Résultat attendu : ', 'bold' => true, 'color' => '065F46'],
            ['text' => $expected],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Anomalie constatée : __________________________________________________________', 'color' => '6B7280'],
        ]);
        return $body;
    }

    private function buildDocumentXml(): string
    {
        $body = '';

        // ===== PAGE DE GARDE =====
        $body .= $this->p('PLAN DE TESTS UTILISATEUR', 'Title');
        $body .= $this->p('GRAVIER.COM — Recette complète de l\'application', '', false, '6B7280');
        $body .= $this->p('Période : du mardi 11 mai au jeudi 13 mai 2026 (3 jours)', '', true);
        $body .= $this->p('Destinataire : Direction Générale / Gestionnaires métier');
        $body .= $this->p('Document généré le : ' . Carbon::now()->locale('fr')->translatedFormat('d F Y à H:i'), '', false, '6B7280');

        // ===== INTRODUCTION =====
        $body .= $this->p('1. Introduction', 'Heading1');
        $body .= $this->p('Ce document liste les tests à exécuter pour valider l\'ensemble des fonctionnalités de la plateforme GRAVIER.COM avant la mise en production officielle. Il est organisé en 3 journées thématiques denses (~8 heures chacune).');
        $body .= $this->pMixed([
            ['text' => 'Comment l\'utiliser : ', 'bold' => true],
            ['text' => 'pour chaque test, suivez les étapes dans l\'ordre, validez le résultat attendu, et cochez la case ☐ si tout fonctionne. Notez les anomalies dans la zone prévue. Les ID des tests permettent de référencer précisément un problème.'],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Conventions :', 'bold' => true],
        ]);
        $body .= $this->bullet('☐ = test non exécuté · ☑ = test validé · ☒ = anomalie détectée');
        $body .= $this->bullet('Les URLs sont relatives au serveur (ex. /login-account). En local, préfixer par http://127.0.0.1:8000');
        $body .= $this->bullet('Hard refresh (Ctrl+Shift+R) recommandé entre les changements de comptes');
        $body .= $this->bullet('Durée moyenne : 8 heures par jour (matin + après-midi)');

        $body .= $this->p('2. Comptes nécessaires pour les tests', 'Heading1');
        $body .= $this->bullet('1 compte Administrateur (accès complet)');
        $body .= $this->bullet('1 compte Gestionnaire');
        $body .= $this->bullet('1 compte Client (particulier)');
        $body .= $this->bullet('1 compte Client à terme (entreprise)');
        $body .= $this->bullet('1 compte Livreur');
        $body .= $this->bullet('1 compte Apporteur d\'affaires');
        $body .= $this->bullet('1 compte Fournisseur (carrière)');

        // ===== JOUR 1 =====
        $body .= $this->p('3. JOUR 1 — Mardi 11 mai 2026', 'Heading1');
        $body .= $this->p('Accès, comptes et expérience client B2C', 'Heading2');
        $body .= $this->pMixed([
            ['text' => 'Objectif : ', 'bold' => true],
            ['text' => 'valider l\'authentification sur les 5 profils, la création de comptes, le flow de réinitialisation de mot de passe, et l\'expérience complète d\'un client (catalogue → commande → paiement → suivi).'],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Durée estimée : ', 'bold' => true],
            ['text' => '8 heures (matin : authentification · après-midi : parcours client)'],
        ]);

        $body .= $this->p('3.1 Pages de connexion et redirections', 'Heading3');
        $body .= $this->testBlock('J1-T01', 'Pages de connexion par profil (visuel)',
            ['Ouvrir /login-account → visuel bleu marine "Espace Administration"',
             'Ouvrir /client/login dans un autre onglet → hero plein écran avec image gravier + camion-benne',
             'Ouvrir /livreur/login → palette verte',
             'Ouvrir /apporteur/login → palette ambrée',
             'Ouvrir /seller/login → palette violette',
             'Comparer visuellement : les 5 pages doivent être nettement distinctes',
             'Vérifier que les icônes dans les champs sont bien séparées du texte saisi'],
            '5 pages chargent sans erreur, chacune a son identité visuelle propre, formulaires bien formatés.');
        $body .= $this->testBlock('J1-T02', 'Flow Mot de passe oublié contextuel',
            ['Sur /login-account, cliquer "Mot de passe oublié ?"',
             'Saisir un email administrateur valide',
             'Vérifier réception d\'un code par email',
             'Saisir le code',
             'Définir un nouveau mot de passe (≥ 4 caractères)',
             'Vérifier la redirection vers /login-account (pas /client/login)',
             'Refaire le test en partant de /client/login → doit revenir sur /client/login',
             'Tester la connexion avec le nouveau mot de passe'],
            'Le flow préserve l\'origine sur les 3 étapes et redirige toujours vers la bonne page login.');

        $body .= $this->p('3.2 Création de comptes', 'Heading3');
        $body .= $this->testBlock('J1-T03', 'Création de compte gestionnaire',
            ['Accéder à /register-account',
             'Remplir : nom et prénoms, téléphone, email, adresse, identifiant, mot de passe, photo (JPG/PNG)',
             'Soumettre',
             'Vérifier l\'absence d\'erreur',
             'Tenter de se connecter avec le nouveau compte sur /login-account'],
            'Compte gestionnaire créé et fonctionnel, redirection vers le tableau de bord après connexion.');
        $body .= $this->testBlock('J1-T04', 'Création de compte client',
            ['Sur /client/login, cliquer "Créer un compte"',
             'Remplir le formulaire complet (choisir particulier ou entreprise)',
             'Soumettre',
             'Se connecter avec le nouveau compte',
             'Vérifier la redirection automatique vers /mon-compte'],
            'Compte client créé, redirection vers l\'espace personnel.');

        $body .= $this->p('3.3 Contrôle d\'accès et session', 'Heading3');
        $body .= $this->testBlock('J1-T05', 'Contrôle d\'accès et session expirée',
            ['Se connecter en admin, naviguer sur /parametre',
             'Effacer les cookies dans le navigateur (DevTools → Application → Cookies → Tout supprimer)',
             'Recharger la page → redirection vers /login-account (pas page 403)',
             'Se connecter en client, tenter d\'accéder à /parametre',
             'Vérifier l\'apparition de la page 403 (mauvais rôle, légitime)'],
            'Session expirée → bonne page login. Mauvais rôle → 403 légitime.');

        $body .= $this->p('3.4 Catalogue et navigation', 'Heading3');
        $body .= $this->testBlock('J1-T06', 'Page d\'accueil et catalogue',
            ['Visiter la page d\'accueil /',
             'Vérifier la présence du logo, du menu, des bannières',
             'Tester la recherche d\'un produit (ex: "Sable", "Gravier")',
             'Filtrer par catégorie',
             'Cliquer sur un produit pour voir sa fiche détaillée'],
            'Catalogue fluide, recherche pertinente, fiche produit complète.');
        $body .= $this->testBlock('J1-T07', 'Ajout au panier et adresse de livraison',
            ['Sur la fiche produit, cliquer "Ajouter au panier"',
             'Aller au panier',
             'Modifier les quantités',
             'Renseigner une adresse de livraison',
             'Vérifier que le coût de livraison est calculé en fonction du km'],
            'Total panier + livraison cohérent, géolocalisation si carte présente.');

        $body .= $this->p('3.5 Devis et commande client', 'Heading3');
        $body .= $this->testBlock('J1-T08', 'Création et validation d\'un devis',
            ['Depuis le panier, cliquer "Demander un devis"',
             'Vérifier l\'enregistrement du devis dans /mon-compte > Devis',
             'Modifier le devis (changer quantité)',
             'Cliquer "Valider et commander"',
             'Choisir le mode de paiement (comptant en agence ou Mobile Money)',
             'Confirmer'],
            'Devis créé, modifiable, transformé en commande avec statut "En attente paiement".');
        $body .= $this->testBlock('J1-T09', 'Paiement Mobile Money',
            ['Sur une commande créée, choisir un mode Mobile Money (Orange / MTN / Moov / Wave)',
             'Valider le paiement',
             'Tester le retour de paiement (mode sandbox si disponible)',
             'Vérifier le passage du statut commande à "Payée"',
             'Vérifier l\'arrivée du paiement dans /mon-compte > Mes paiements'],
            'Paiement Mobile Money traité, statut mis à jour, reçu disponible.');

        $body .= $this->p('3.6 Espace client', 'Heading3');
        $body .= $this->testBlock('J1-T10', 'Tableau de bord client (/mon-compte)',
            ['Connecté en client, aller sur /mon-compte',
             'Vérifier les sections : Commandes, Devis, Paiements, Adresses, Points fidélité',
             'Cliquer "Mes paiements" → vérifier l\'historique',
             'Télécharger un reçu de paiement en PDF',
             'Vérifier que le PDF contient montant, date, mode, référence, logo'],
            'Tableau de bord complet, PDF reçu propre et imprimable.');
        $body .= $this->testBlock('J1-T11', 'Demande client à terme et suivi livraison',
            ['Sur /mon-compte, cliquer "Demande client à terme"',
             'Remplir le formulaire et soumettre',
             'Vérifier que la demande est enregistrée en attente',
             'Sur une commande livrée, cliquer "Suivre la livraison"',
             'Vérifier la carte avec position du livreur (si Leaflet actif)'],
            'Demande à terme enregistrée, carte de suivi affichée.');
        $body .= $this->testBlock('J1-T12', 'Retour produit',
            ['Sur une commande livrée, cliquer "Retour produit"',
             'Sélectionner les articles à retourner',
             'Indiquer le motif',
             'Soumettre'],
            'Demande de retour enregistrée et visible dans l\'espace client.');

        // ===== JOUR 2 =====
        $body .= $this->p('4. JOUR 2 — Mercredi 12 mai 2026', 'Heading1');
        $body .= $this->p('Modules métier financiers (Créances + Dettes)', 'Heading2');
        $body .= $this->pMixed([
            ['text' => 'Objectif : ', 'bold' => true],
            ['text' => 'valider les 5 modules métier de gestion financière : Clients à terme, Comptant, Fournisseurs, Livreurs, Apporteurs d\'affaires.'],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Durée estimée : ', 'bold' => true],
            ['text' => '8 heures (modules métier en chaîne — connectez-vous en admin)'],
        ]);

        $body .= $this->p('4.1 Module Clients à terme', 'Heading3');
        $body .= $this->testBlock('J2-T01', 'Liste clients à terme et factures',
            ['Menu Clients → Client à terme → Clients',
             'Vérifier l\'affichage de la liste (Code, Nom, Plafond crédit, Délai paiement)',
             'Menu Clients → Client à terme → Factures',
             'Vérifier les 17 colonnes du fichier Excel (N°, Date, Client, Produit, Quantité, PU HT, TTC, Échéance, Statut...)',
             'Vérifier qu\'une facture en retard ≥ seuil d\'alerte est affichée en rouge'],
            'Liste et factures complètes. Alerte rouge active sur les retards.');
        $body .= $this->testBlock('J2-T02', 'Paiement multi-tranches et reçu PDF',
            ['Menu Clients → Client à terme → Paiements',
             'Cliquer "Enregistrer un paiement"',
             'Sélectionner une facture non soldée',
             'Renseigner un montant partiel (< montant_total), mode, date, référence',
             'Soumettre → vérifier le reçu PDF (format A5)',
             'Réouvrir le modal → vérifier l\'historique avec la 1ère tranche',
             'Compléter le solde avec une 2e tranche',
             'Télécharger les 2 reçus → vérifier les numéros distincts (RC-AAAA-001, RC-AAAA-002)'],
            'Paiement multi-tranches OK, 2 reçus PDF distincts, statut facture mis à jour.');
        $body .= $this->testBlock('J2-T03', 'Section "À relancer aujourd\'hui"',
            ['Menu Clients → Client à terme → Relances',
             'Vérifier l\'encart jaune en haut "À relancer aujourd\'hui"',
             'Cliquer sur "Relancer" d\'une ligne',
             'Vérifier que le modal est pré-rempli (client, facture, niveau auto-calculé)',
             'Compléter type d\'appel, réponse client, action suivante',
             'Enregistrer'],
            'Relance enregistrée, ligne disparaît de la section "À relancer".');
        $body .= $this->testBlock('J2-T04', 'Synthèse créances à terme',
            ['Menu Clients → Client à terme → Synthèse',
             'Vérifier les 4 KPI cards : Total facturé, Encaissé, Reste à encaisser, Retard moyen',
             'Vérifier la barre de répartition (À échoir / Partielles / Impayées)',
             'Vérifier le Top 5 débiteurs (nom complet du client, pas de données techniques affichées)',
             'Cliquer sur les 4 boutons d\'actions rapides → vérifier les redirections'],
            'Synthèse premium, KPI cohérents, Top 5 lisible, navigation OK.');

        $body .= $this->p('4.2 Module Comptant (paiement en agence)', 'Heading3');
        $body .= $this->testBlock('J2-T05', 'Commandes comptant et encaissements',
            ['Menu Clients → Client ordinaire → Commandes',
             'Vérifier toutes les colonnes (avec date limite calculée auto)',
             'Identifier une commande "En retard" → ligne en rouge',
             'Menu Clients → Client ordinaire → Encaissements Agence',
             'Cliquer "Enregistrer un paiement en agence"',
             'Choisir une commande, agence, caissier, mode (Mobile Money), montant',
             'Valider et télécharger le reçu PDF'],
            'Liste commandes complète, encaissement enregistré, reçu PDF imprimable.');
        $body .= $this->testBlock('J2-T06', 'CRUD Agences',
            ['Menu Divers → Agences',
             'Créer une agence test : Code AG-TEST, Nom Agence Test, Adresse Cocody',
             'Modifier la fiche (changer téléphone)',
             'Désactiver l\'agence',
             'Réactiver',
             'Tenter de la supprimer (refus attendu si commandes liées)'],
            'CRUD complet, protection contre suppression si dépendances.');
        $body .= $this->testBlock('J2-T07', 'Synthèse comptant',
            ['Menu Clients → Client ordinaire → Synthèse',
             'Vérifier les KPI : commandes total, encaissées, en retard, taux',
             'Vérifier la répartition par agence'],
            'Synthèse cohérente avec les données du module.');

        $body .= $this->p('4.3 Module Fournisseurs', 'Heading3');
        $body .= $this->testBlock('J2-T08', 'CRUD fournisseurs et enlèvements',
            ['Menu Fournisseurs → Fournisseurs',
             'Vérifier les colonnes (Code, Nom, Type, Produit principal, Délai paiement)',
             'Créer un nouveau fournisseur test',
             'Menu Fournisseurs → Enlèvements',
             'Vérifier la liste avec colonnes Excel',
             'Vérifier qu\'un enlèvement échu impayé est en rouge'],
            'CRUD fournisseurs et liste enlèvements opérationnels.');
        $body .= $this->testBlock('J2-T09', 'Paiement fournisseur et synthèse',
            ['Menu Fournisseurs → Paiements',
             'Cliquer "Enregistrer un paiement fournisseur"',
             'Choisir un enlèvement non soldé',
             'Renseigner montant, mode, référence',
             'Valider et télécharger le reçu PDF (préfixe PF-AAAA-XXX)',
             'Menu Fournisseurs → Synthèse → vérifier KPI cohérents'],
            'Paiement fournisseur enregistré, reçu généré, synthèse cohérente.');

        $body .= $this->p('4.4 Module Livreurs', 'Heading3');
        $body .= $this->testBlock('J2-T10', 'CRUD livreurs avec type véhicule',
            ['Menu Livreur → Livreurs',
             'Vérifier les colonnes (Code, Nom, Téléphone, Type véhicule, Total dû)',
             'Créer un nouveau livreur en sélectionnant un type de véhicule dans le select (Tricycle, Camion 5T, etc.)',
             'Modifier sa fiche',
             'Vérifier que le total dû est calculé automatiquement'],
            'CRUD livreurs OK avec lien vers types de véhicules.');
        $body .= $this->testBlock('J2-T11', 'Cycle de paiement livreur',
            ['Menu Livreur → Paiements',
             'Vérifier l\'encart "Prochain cycle de paiement" en haut (date + couleur)',
             'Aller dans Paramètres → Livreurs',
             'Changer la fréquence en "Quotidien"',
             'Revenir sur Livreurs → Paiements → vérifier que l\'encart est passé en vert "Aujourd\'hui"',
             'Remettre la fréquence à "Hebdomadaire" (Vendredi par défaut)'],
            'Cycle paramétrable, encart prend en compte le changement dynamiquement.');
        $body .= $this->testBlock('J2-T12', 'Paiement livreur multi-tranches et bordereau',
            ['Sur Livreur → Paiements, cliquer "Enregistrer un paiement"',
             'Choisir un livreur, payer une partie du montant dû',
             'Vérifier la mise à jour de "Reste à payer"',
             'Réouvrir le modal → vérifier l\'historique des tranches',
             'Compléter le solde',
             'Télécharger les 2 bordereaux (préfixe PL-AAAA-XXX)'],
            'Multi-tranches OK, 2 bordereaux distincts générés.');
        $body .= $this->testBlock('J2-T13', 'Liste livraisons et synthèse',
            ['Menu Livreur → Livraisons → vérifier la liste',
             'Identifier une livraison "En contestation" → ligne mise en évidence (table-warning)',
             'Menu Livreur → Synthèse → vérifier KPI et principaux créanciers'],
            'Liste et synthèse livreurs cohérentes.');

        $body .= $this->p('4.5 Module Apporteurs d\'affaires', 'Heading3');
        $body .= $this->testBlock('J2-T14', 'CRUD apporteurs et fallback taux',
            ['Menu Apporteur → Apporteurs',
             'Vérifier les colonnes (Code, Nom, Taux commission, Solde)',
             'Créer un apporteur SANS renseigner le taux',
             'Générer une commission via une nouvelle commande client liée à cet apporteur',
             'Vérifier que la commission utilise le taux standard de la configuration (ex: 3%)'],
            'Fallback taux_commission_standard fonctionnel : le système prend la valeur par défaut.');
        $body .= $this->testBlock('J2-T15', 'Commissions et paiement (règle métier)',
            ['Menu Apporteur → Commissions',
             'Identifier une commission "En attente paiement client"',
             'Tenter de la payer → vérifier que c\'est refusé ou non listée comme payable',
             'Identifier une commission "Due" (client a déjà payé)',
             'Enregistrer le paiement',
             'Télécharger le reçu (préfixe PA-AAAA-XXX)'],
            'Règle respectée : commission due UNIQUEMENT après paiement client.');
        $body .= $this->testBlock('J2-T16', 'Synthèse apporteurs',
            ['Menu Apporteur → Synthèse',
             'Vérifier KPI et top apporteurs'],
            'Synthèse cohérente.');

        // ===== JOUR 3 =====
        $body .= $this->p('5. JOUR 3 — Jeudi 13 mai 2026', 'Heading1');
        $body .= $this->p('Récaps consolidés, paramétrage, référentiels, automatisations, divers', 'Heading2');
        $body .= $this->pMixed([
            ['text' => 'Objectif : ', 'bold' => true],
            ['text' => 'valider les vues consolidées, la configuration centralisée, les listes de référence, les automatisations métier et les fonctionnalités diverses.'],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Durée estimée : ', 'bold' => true],
            ['text' => '8 heures (matin : récaps + paramétrage · après-midi : référentiels + automations + finale)'],
        ]);

        $body .= $this->p('5.1 Tableaux de bord consolidés', 'Heading3');
        $body .= $this->testBlock('J3-T01', 'Tableau de bord gestionnaire',
            ['Aller sur /gestionnaire/home',
             'Vérifier les 4 KPI cards (Revenu, Commandes actives, Catalogue, Gain mois)',
             'Vérifier la répartition des commandes (graphique stacked bar)',
             'Vérifier la section "Communauté" (clients, livreurs, fournisseurs, apporteurs)',
             'Vérifier le Top 5 produits'],
            'Dashboard premium complet, données cohérentes.');
        $body .= $this->testBlock('J3-T02', 'Récap Créances + Guide',
            ['Aller sur /recap-creances/tableau-de-bord',
             'Vérifier les KPI consolidés Terme + Comptant',
             'Vérifier le Top 5 débiteurs (noms lisibles, pas de données techniques)',
             'Cliquer le bouton "Guide d\'utilisation" en haut',
             'Vérifier l\'apparition de la modale avec 7 sections (objectif, sources, actualisation, code couleur, onglets, actions, contact)'],
            'Récap clair, modale guide fonctionnelle.');
        $body .= $this->testBlock('J3-T03', 'Récap Dettes + Guide',
            ['Aller sur /recap-dettes/tableau-bord',
             'Vérifier les KPI consolidés Fournisseurs + Livreurs + Apporteurs',
             'Cliquer le bouton "Guide d\'utilisation" → modale avec en-tête rouge'],
            'Récap dettes opérationnel, modale fonctionnelle.');

        $body .= $this->p('5.2 Paramètres système', 'Heading3');
        $body .= $this->testBlock('J3-T04', 'Onglets de Paramètres',
            ['Aller sur /parametre',
             'Naviguer dans les 8 onglets : Configuration générale, Livraison & TVA, Gestionnaires, Prix personnalisés, Créances terme, Comptant/Agence, Livreurs, Apporteurs',
             'Sur l\'onglet "Apporteurs", changer le taux de commission standard de 3 à 5',
             'Sauvegarder',
             'Recharger la page → vérifier la persistance de la valeur'],
            '8 onglets fonctionnels, persistance des valeurs OK.');
        $body .= $this->testBlock('J3-T05', 'Impact réel d\'un changement de paramètre',
            ['Toujours sur l\'onglet Apporteurs, le taux étant à 5%',
             'Aller créer une commande qui génère une commission (commande d\'un client avec un parrain apporteur)',
             'Vérifier que la commission utilise 5% (et non plus 3%)',
             'Refaire un test similaire : changer "delai_relance_standard" puis vérifier que la section "À relancer" recalcule'],
            'Les paramètres sont réellement câblés en logique métier (pas que de l\'affichage).');

        $body .= $this->p('5.3 Référentiels éditables', 'Heading3');
        $body .= $this->testBlock('J3-T06', 'CRUD Statuts métier',
            ['Menu Divers → Statuts métier',
             'Vérifier les 27 statuts seedés (filtrer par domaine pour ne voir qu\'un module à la fois)',
             'Modifier la couleur d\'un statut (ex: passer "À échoir" en bg-primary bleu)',
             'Aller sur /clients-terme/factures',
             'Vérifier que le badge "À échoir" a changé de couleur SANS aucun déploiement'],
            'Référentiel statuts entièrement éditable depuis l\'interface, impact immédiat.');
        $body .= $this->testBlock('J3-T07', 'CRUD Types véhicules livreurs',
            ['Menu Divers → Types véhicules livreurs',
             'Vérifier les 5 types seedés (Tricycle, Camion 5T, Camion 10T, Camion 25T, Benne)',
             'Créer un type "Pickup 1T" avec capacité 1 tonne',
             'Aller créer un livreur → vérifier que "Pickup 1T" apparaît dans le select',
             'Tenter de supprimer un type rattaché à un livreur → vérifier le refus (protection)'],
            'Référentiel types véhicules opérationnel, protection référentielle active.');

        $body .= $this->p('5.4 Automatisations métier', 'Heading3');
        $body .= $this->testBlock('J3-T08', 'Annulation auto des commandes périmées',
            ['Demander au développeur de lancer en CLI : php artisan gravier:annuler-comptant-perimes --dry-run',
             'Vérifier la liste des commandes éligibles affichée',
             'Lancer sans --dry-run',
             'Aller sur /comptant/commandes → vérifier que ces commandes sont passées en "Annulée"',
             'Vérifier que la note contient le tag "[Auto-annulée le ... — délai dépassé sans paiement]"'],
            'Job d\'annulation auto opérationnel avec traçabilité.');
        $body .= $this->testBlock('J3-T09', 'Confirmations SweetAlert2 sur les suppressions',
            ['Sur n\'importe quel écran de liste (Agences, Produits, Clients, etc.), cliquer un bouton "Supprimer"',
             'Vérifier l\'apparition d\'une boîte de dialogue moderne (pas alert navigateur)',
             'Cliquer "Annuler" → rien ne doit être supprimé',
             'Recommencer et confirmer → la suppression doit s\'effectuer'],
            'Confirmations modales actives partout, aucune suppression accidentelle possible.');

        $body .= $this->p('5.5 Fonctionnalités diverses', 'Heading3');
        $body .= $this->testBlock('J3-T10', 'Grands livres PDF',
            ['Menu Les grands livres → Clients ordinaires',
             'Cliquer Export PDF',
             'Vérifier le contenu (soldes par client cohérents)',
             'Refaire pour Clients à terme, Livreurs, Fournisseurs'],
            '4 grands livres PDF générés correctement.');
        $body .= $this->testBlock('J3-T11', 'Code promo',
            ['Menu Création de code promo',
             'Créer un code promo "TEST20" à 20%',
             'Tester l\'application dans un panier client',
             'Vérifier la réduction appliquée'],
            'Code promo fonctionnel.');
        $body .= $this->testBlock('J3-T12', 'Régions, villes, blog, bannières',
            ['Menu Divers → Les régions → créer / modifier / supprimer une région',
             'Idem pour les villes',
             'Menu Divers → Blog → créer un article test',
             'Idem pour Bannière'],
            'CRUD géographique et contenu opérationnels.');
        $body .= $this->testBlock('J3-T13', 'FNE (facturation normalisée)',
            ['Sur une facture, cliquer "Générer FNE"',
             'Vérifier le retour API (succès / échec)',
             'Vérifier la sauvegarde du QR code et du numéro FNE'],
            'Intégration FNE opérationnelle (selon contexte API).');

        // ===== ANNEXES =====
        $body .= $this->p('6. Annexes', 'Heading1');

        $body .= $this->p('6.1 Synthèse de validation', 'Heading2');
        $body .= $this->p('Récapitulatif à compléter en fin de période :');
        $body .= $this->bullet('Jour 1 (Accès + Client B2C) : ☐ Validé      ☐ Anomalies bloquantes      ☐ Anomalies mineures');
        $body .= $this->bullet('Jour 2 (Modules métier financiers) : ☐ Validé      ☐ Anomalies bloquantes      ☐ Anomalies mineures');
        $body .= $this->bullet('Jour 3 (Récaps + Config + Divers) : ☐ Validé      ☐ Anomalies bloquantes      ☐ Anomalies mineures');

        $body .= $this->p('6.2 Anomalies bloquantes recensées', 'Heading2');
        for ($i = 1; $i <= 5; $i++) {
            $body .= $this->p("Anomalie #{$i} : ____________________________________________________________________", '', false, '6B7280');
            $body .= $this->p("    Écran concerné : _______________________________________________________________", '', false, '6B7280');
            $body .= $this->p("    ID test : _____________     Sévérité : ☐ Bloquante ☐ Majeure ☐ Mineure", '', false, '6B7280');
        }

        $body .= $this->p('6.3 Décision finale', 'Heading2');
        $body .= $this->p('À cocher après recette complète :');
        $body .= $this->bullet('☐ GO PROD — Tous les tests validés ou anomalies non-bloquantes uniquement');
        $body .= $this->bullet('☐ NO GO — Anomalies bloquantes à corriger avant mise en production');
        $body .= $this->bullet('☐ GO PARTIEL — Mise en production avec périmètre restreint (préciser ci-dessous)');
        $body .= $this->p('Périmètre restreint éventuel : _________________________________________________', '', false, '6B7280');

        $body .= $this->p('6.4 Signatures', 'Heading2');
        $body .= $this->p('Testeur principal : ______________________________________   Date : ___ / ___ / 2026');
        $body .= $this->p('Validation DG : ____________________________________________   Date : ___ / ___ / 2026');
        $body .= $this->p('Visa développeur : __________________________________________   Date : ___ / ___ / 2026');

        $body .= $this->p('6.5 Contacts', 'Heading2');
        $body .= $this->bullet('Support technique : developpeur@gravier.com');
        $body .= $this->bullet('Service recouvrement : recouvrement@gravier.com');
        $body .= $this->bullet('Service trésorerie : tresorerie@gravier.com');
        $body .= $this->bullet('Hébergeur : LWS (panel admin)');

        // Wrap dans le document XML complet
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">';
        $xml .= '<w:body>';
        $xml .= $body;
        $xml .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>';
        $xml .= '</w:body>';
        $xml .= '</w:document>';

        return $xml;
    }
}
