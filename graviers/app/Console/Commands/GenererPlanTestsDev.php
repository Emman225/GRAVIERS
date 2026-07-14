<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use Carbon\Carbon;

class GenererPlanTestsDev extends Command
{
    protected $signature = 'gravier:plan-tests-dev
        {--out= : Chemin de sortie. Défaut : storage/app/public/plan-tests-dev.docx}';

    protected $description = 'Génère un plan de tests technique (dev) sur 3 jours pour validation locale avant déploiement.';

    public function handle(): int
    {
        $out = $this->option('out') ?: storage_path('app/public/plan-tests-dev.docx');
        $dir = dirname($out);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $documentXml  = $this->buildDocumentXml();
        $stylesXml    = $this->buildStylesXml();
        $rels         = $this->buildRels();
        $docRels      = $this->buildDocRels();
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
        $this->info("Plan de tests DEV généré : {$out} ({$size} Ko)");
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
                <w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="fr-FR"/>
            </w:rPr>
        </w:rPrDefault>
        <w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>
    </w:docDefaults>
    <w:style w:type="paragraph" w:styleId="Title">
        <w:name w:val="Title"/>
        <w:pPr><w:spacing w:before="240" w:after="240"/><w:jc w:val="center"/></w:pPr>
        <w:rPr><w:b/><w:sz w:val="44"/><w:color w:val="0A2540"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading1">
        <w:name w:val="heading 1"/>
        <w:pPr><w:spacing w:before="360" w:after="120"/><w:pBdr><w:bottom w:val="single" w:sz="12" w:color="047857"/></w:pBdr></w:pPr>
        <w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="047857"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading2">
        <w:name w:val="heading 2"/>
        <w:pPr><w:spacing w:before="240" w:after="100"/></w:pPr>
        <w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="065F46"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Heading3">
        <w:name w:val="heading 3"/>
        <w:pPr><w:spacing w:before="180" w:after="80"/></w:pPr>
        <w:rPr><w:b/><w:sz w:val="22"/><w:color w:val="0A2540"/></w:rPr>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Code">
        <w:name w:val="Code"/>
        <w:pPr><w:shd w:val="clear" w:color="auto" w:fill="F3F4F6"/><w:ind w:left="240"/></w:pPr>
        <w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="20"/><w:color w:val="0F172A"/></w:rPr>
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
            if (!empty($r['bold']) || !empty($r['color']) || !empty($r['italic']) || !empty($r['mono'])) {
                $rPr .= '<w:rPr>';
                if (!empty($r['bold']))   $rPr .= '<w:b/>';
                if (!empty($r['italic'])) $rPr .= '<w:i/>';
                if (!empty($r['color']))  $rPr .= '<w:color w:val="' . $r['color'] . '"/>';
                if (!empty($r['mono']))   $rPr .= '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/>';
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

    private function code(string $command): string
    {
        $pPr = "<w:pPr><w:pStyle w:val=\"Code\"/></w:pPr>";
        $body = "<w:r><w:rPr><w:rFonts w:ascii=\"Consolas\" w:hAnsi=\"Consolas\"/></w:rPr><w:t xml:space=\"preserve\">$ " . $this->xml($command) . "</w:t></w:r>";
        return "<w:p>{$pPr}{$body}</w:p>";
    }

    private function step(int $num, string $text): string
    {
        $pPr = "<w:pPr><w:ind w:left=\"360\" w:hanging=\"200\"/></w:pPr>";
        $body = "<w:r><w:rPr><w:b/></w:rPr><w:t xml:space=\"preserve\">{$num}.  </w:t></w:r><w:r><w:t xml:space=\"preserve\">" . $this->xml($text) . "</w:t></w:r>";
        return "<w:p>{$pPr}{$body}</w:p>";
    }

    /**
     * Bloc de test technique : ID, titre, étapes (mix texte/code), check, anomalie.
     * Étape avec préfixe "$ " = commande shell (style Code).
     */
    private function testBlock(string $id, string $title, array $steps, string $check): string
    {
        $body = $this->pMixed([
            ['text' => "☐  {$id} — ", 'bold' => true, 'color' => '047857'],
            ['text' => $title, 'bold' => true],
        ]);
        $body .= $this->p('Étapes :', '', true);
        $num = 1;
        foreach ($steps as $s) {
            if (str_starts_with($s, '$ ')) {
                $body .= $this->code(substr($s, 2));
            } else {
                $body .= $this->step($num++, $s);
            }
        }
        $body .= $this->pMixed([
            ['text' => 'Check : ', 'bold' => true, 'color' => '065F46'],
            ['text' => $check],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Anomalie / Note : __________________________________________________________', 'color' => '6B7280'],
        ]);
        return $body;
    }

    private function buildDocumentXml(): string
    {
        $body = '';

        // ===== PAGE DE GARDE =====
        $body .= $this->p('PLAN DE TESTS — VALIDATION DÉVELOPPEUR', 'Title');
        $body .= $this->p('GRAVIER.COM — Recette technique locale avant déploiement', '', false, '6B7280');
        $body .= $this->p('Période : du mardi 11 mai au jeudi 13 mai 2026 (3 jours, ~24 h)', '', true);
        $body .= $this->p('Cible : développeur unique en environnement local', '', false, '6B7280');
        $body .= $this->p('Document généré le : ' . Carbon::now()->locale('fr')->translatedFormat('d F Y à H:i'), '', false, '6B7280');

        // ===== INTRODUCTION =====
        $body .= $this->p('1. Contexte', 'Heading1');
        $body .= $this->p('Plan de validation technique condensé pour un développeur seul, en local, avant la mise en production sur LWS. Il combine la couverture fonctionnelle (B2C + back-office) avec des vérifications techniques (DB, migrations, cron, caches, logs, console DevTools).');
        $body .= $this->pMixed([
            ['text' => 'Méthode : ', 'bold' => true],
            ['text' => 'pour chaque test, exécuter les étapes (commandes shell ou clics navigateur), vérifier le check, cocher ☐. Les commandes en monospace s\'exécutent dans le dossier du projet.'],
        ]);
        $body .= $this->pMixed([
            ['text' => 'Légende :', 'bold' => true],
        ]);
        $body .= $this->bullet('☐ = à exécuter   ☑ = OK   ☒ = anomalie à corriger avant prod');
        $body .= $this->bullet('Étapes préfixées par $ = commandes shell (terminal dans la racine projet)');
        $body .= $this->bullet('Étapes numérotées sans $ = actions navigateur ou code éditeur');

        $body .= $this->p('2. Environnement requis', 'Heading1');
        $body .= $this->bullet('PHP ≥ 8.1, MySQL/MariaDB démarré');
        $body .= $this->bullet('Navigateur avec DevTools ouverts (Console + Network) pour repérer 4xx/5xx/JS errors');
        $body .= $this->bullet('php artisan serve actif sur http://127.0.0.1:8000');
        $body .= $this->bullet('Logs en suivi continu : tail -f storage/logs/laravel.log (terminal séparé)');
        $body .= $this->bullet('Branch git propre, .env de dev configuré');

        // ===== JOUR 1 =====
        $body .= $this->p('3. JOUR 1 — Mardi 11 mai 2026', 'Heading1');
        $body .= $this->p('Foundation, auth, contrôle d\'accès, smoke CRUD', 'Heading2');
        $body .= $this->pMixed([['text' => 'Durée cible : ', 'bold' => true], ['text' => '8 h (matinée fondations · après-midi auth + frontend public)']]);

        $body .= $this->p('3.1 État du repo et de la DB', 'Heading3');
        $body .= $this->testBlock('D1-01', 'Branch git + dépendances',
            ['$ git status',
             '$ git log --oneline -10',
             '$ composer install',
             'Vérifier que vendor/ contient les packages attendus (PhpSpreadsheet, DomPDF, Sanctum…)'],
            'Branch propre (ou WIP attendu), composer.lock cohérent, aucune dépendance manquante.');
        $body .= $this->testBlock('D1-02', 'État des migrations',
            ['$ php artisan migrate:status',
             'Vérifier qu\'aucune migration n\'est en "Pending"',
             '$ php artisan migrate --force (si pending)',
             'Compter le nombre total de migrations'],
            'Toutes les migrations passées. Aucune en attente.');
        $body .= $this->testBlock('D1-03', 'Intégrité DB (tables critiques)',
            ['$ php artisan tinker',
             '>>> Schema::hasTable(\'configuration\') && Schema::hasTable(\'agence\') && Schema::hasTable(\'statut_metier\') && Schema::hasTable(\'type_vehicule_livreur\')',
             '>>> \\App\\Models\\StatutMetier::count()  // attendu 27',
             '>>> \\App\\Models\\TypeVehiculeLivreur::count()  // attendu 5',
             '>>> \\DB::table(\'mode_paiement\')->count()  // attendu ≥ 9'],
            'Toutes les tables des chantiers B+C présentes, seeds en place.');
        $body .= $this->testBlock('D1-04', 'Routes sans collision',
            ['$ php artisan route:list --columns=method,uri,name',
             'Compter le nombre de routes',
             'Identifier les éventuels doublons de name'],
            'Aucune route nommée en doublon, aucune erreur d\'enregistrement.');
        $body .= $this->testBlock('D1-05', 'Caches Laravel propres',
            ['$ php artisan view:clear',
             '$ php artisan route:clear',
             '$ php artisan config:clear',
             '$ php artisan cache:clear'],
            'Caches nettoyés sans erreur.');

        $body .= $this->p('3.2 Authentification et redirections contextuelles', 'Heading3');
        $body .= $this->testBlock('D1-06', '5 pages de connexion — visuel + DevTools',
            ['Ouvrir DevTools (F12) onglet Console',
             'Visiter /login-account → vérifier 0 erreur console, design bleu navy',
             'Visiter /client/login → vérifier hero plein écran + SVG camion',
             'Visiter /livreur/login, /apporteur/login, /seller/login',
             'Sur chacune, vérifier que les icônes des champs sont séparées (flexbox, pas chevauchement)'],
            '5 pages chargent sans erreur JS/CSS. Champs avec icône à gauche + séparateur + texte.');
        $body .= $this->testBlock('D1-07', 'Flow Mot de passe oublié contextuel',
            ['Sur /login-account, cliquer "Mot de passe oublié ?" → URL contient ?from=admin',
             'Soumettre un email admin valide',
             '$ tail storage/logs/laravel.log  // observer envoi du mail (mode log si MAIL_MAILER=log)',
             'Récupérer le code dans le log et le saisir',
             'Définir un nouveau mdp',
             'Vérifier redirect vers /login-account (et pas client.login)',
             'Refaire depuis /client/login → doit revenir sur /client/login'],
            'Le paramètre from préserve l\'origine sur tout le flow (demandeEmail → code → passwordModify).');
        $body .= $this->testBlock('D1-08', 'Création de compte gestionnaire publique',
            ['Visiter /register-account directement (pas via le bouton, qui a été retiré de login-account)',
             'Remplir le formulaire complet avec une photo',
             'Soumettre',
             'Vérifier l\'absence d\'erreur "type_user_id on null"',
             '$ php artisan tinker  >>> \\App\\Models\\User::latest()->first()->type_user_id  // doit pointer sur gestionnaire'],
            'Compte créé avec type_user_id correct, vue register sans extends layout.main (donc sans navbar admin).');
        $body .= $this->testBlock('D1-09', 'Contrôle d\'accès par rôle',
            ['Se connecter en client',
             'Tenter /parametre → vérifier 403 légitime',
             'Tenter /clients-terme/factures → 403',
             'Se déconnecter, vider session',
             'Tenter /parametre sans auth → redirection vers /login-account (pas 403, pas client.login)'],
            'Session expirée → redirection contextuelle. Mauvais rôle → 403 (legitime).');
        $body .= $this->testBlock('D1-10', 'Erreurs console sur pages auth',
            ['Sur chacune des 5 pages login, observer DevTools Console',
             'Vérifier l\'absence d\'erreur color-modes.js (guard ajouté)',
             'Vérifier l\'absence de 404 sur les CSS premium-auth.css ?v=4.0'],
            'Console propre sur les 5 pages login.');

        $body .= $this->p('3.3 Frontend public', 'Heading3');
        $body .= $this->testBlock('D1-11', 'Homepage et navigation',
            ['Visiter / (en mode anonyme)',
             'DevTools : aucune erreur 4xx/5xx',
             'Tester recherche, filtre catégorie, pagination'],
            'Frontend fluide. Aucun appel KO en réseau.');
        $body .= $this->testBlock('D1-12', 'Routes anonymes vs auth',
            ['Tenter d\'accéder à /mon-compte sans login → redirige sur /client/login',
             'Tenter /panier-en-commande sans login → idem',
             'Vérifier que /produits et /detail-produit-X sont publiques'],
            'Auth guards sur les pages client privées, public sur le catalogue.');

        $body .= $this->p('3.4 Smoke CRUD basique', 'Heading3');
        $body .= $this->testBlock('D1-13', 'CRUD Produits',
            ['Login admin, /products-list',
             'Créer un produit "TEST-DEV-1"',
             'Modifier (changer prix)',
             'Vérifier SweetAlert2 sur le bouton supprimer',
             'Supprimer (confirmer)',
             '$ php artisan tinker  >>> \\App\\Models\\Produit::onlyTrashed()->where(\'nom\',\'TEST-DEV-1\')->exists()  // attendu true'],
            'CRUD complet, soft delete fonctionnel.');
        $body .= $this->testBlock('D1-14', 'CRUD Clients',
            ['/list-client puis /list-client-a-terme',
             'Création client particulier puis entreprise (avec NCC/RCCM)',
             'Modification fiche',
             'Vérification dans le profil client'],
            'Clients créés, type_client correct, soft delete.');

        // ===== JOUR 2 =====
        $body .= $this->p('4. JOUR 2 — Mercredi 12 mai 2026', 'Heading1');
        $body .= $this->p('Modules métier (B2C + back-office) — 9 chaînes de bout en bout', 'Heading2');
        $body .= $this->pMixed([['text' => 'Durée cible : ', 'bold' => true], ['text' => '8 h (1h par module en moyenne, focus sur les chemins critiques)']]);

        $body .= $this->p('4.1 Cycle complet B2C client', 'Heading3');
        $body .= $this->testBlock('D2-01', 'Devis → commande → paiement Mobile Money',
            ['Login client, ajouter un produit au panier',
             'Demander un devis',
             'Valider en commande',
             'Choisir Orange Money (id=2 dans mode_paiement)',
             'Simuler retour de paiement (ou marquer manuellement dans la DB si sandbox indisponible)',
             '$ php artisan tinker  >>> \\App\\Models\\Commande::latest()->first()->montantPayeComptant()'],
            'Commande créée, paiement enregistré, montantPayeComptant() renvoie le montant.');
        $body .= $this->testBlock('D2-02', 'Demande passage à terme + parrainage apporteur',
            ['Depuis /mon-compte, soumettre une demande client à terme',
             'En admin, approuver (set client_a_terme = 1)',
             'Si code_parrain renseigné, vérifier la création de CommissionApporteur'],
            'Bascule terme effective, commission générée si parrainage.');

        $body .= $this->p('4.2 Module Clients à terme', 'Heading3');
        $body .= $this->testBlock('D2-03', 'Factures à terme + alerte rouge',
            ['/clients-terme/factures',
             'Vérifier 17 colonnes (Excel)',
             'En tinker : créer une facture fictive avec date_echeance < today - seuil_alerte_retard',
             'Recharger → vérifier que la ligne est rouge (table-danger)'],
            'Alerte basée sur seuil_alerte_retard configurable.');
        $body .= $this->testBlock('D2-04', 'Paiement multi-tranches + reçu PDF',
            ['/clients-terme/paiements',
             'Enregistrer un paiement partiel sur une facture (< montant_total)',
             'Réouvrir le modal → vérifier historique (1 tranche)',
             'Compléter le solde → 2 tranches',
             'Télécharger le reçu PDF de chaque tranche',
             '$ ls storage/fonts/  // si vide, dompdf rebuild auto'],
            'Multi-tranches OK, 2 PDFs distincts, numéro de reçu auto-incrémenté.');
        $body .= $this->testBlock('D2-05', 'Section "À relancer aujourd\'hui"',
            ['/clients-terme/relances',
             'Vérifier l\'encart jaune avec factures éligibles',
             'En tinker : insérer une RelanceClientTerme datée d\'aujourd\'hui sur une facture éligible',
             'Recharger → la facture doit disparaître de la section (couverte par relance récente)'],
            'Logique : retard ≥ delai_relance_standard ET aucune relance dans les delai_relance_standard derniers jours.');

        $body .= $this->p('4.3 Module Comptant', 'Heading3');
        $body .= $this->testBlock('D2-06', 'Encaissement agence + reçu',
            ['/comptant/encaissements',
             'Enregistrer un paiement en agence (sélection Agence + caissier)',
             'Vérifier la génération du PDF format A5',
             '$ php artisan tinker  >>> \\App\\Models\\Paiement::latest()->first()->agence_id  // non null'],
            'Paiement avec agence_id renseigné, reçu PDF.');
        $body .= $this->testBlock('D2-07', 'Annulation auto périmées (DRY)',
            ['$ php artisan gravier:annuler-comptant-perimes --dry-run',
             'Observer les commandes éligibles',
             '$ php artisan gravier:annuler-comptant-perimes  // exécution réelle',
             'Vérifier que les statuts sont passés en "Annulée"',
             'Vérifier que la note contient le tag "[Auto-annulée le ...]"'],
            'Command idempotente, traçabilité via note.');

        $body .= $this->p('4.4 Module Fournisseurs', 'Heading3');
        $body .= $this->testBlock('D2-08', 'CRUD + enlèvement + paiement',
            ['Créer un fournisseur de type "carrière"',
             'Enregistrer un enlèvement',
             '/fournisseurs/paiements → enregistrer un paiement partiel',
             'Vérifier le PDF reçu (format A5, prefix PF-AAAA-XXX)'],
            'Chaîne complète OK.');

        $body .= $this->p('4.5 Module Livreurs', 'Heading3');
        $body .= $this->testBlock('D2-09', 'Création livreur avec type véhicule',
            ['/livreurs/livreurs → créer un livreur',
             'Vérifier que le select "Type véhicule" propose les 5 types seedés',
             'Si le select n\'apparaît pas dans le form actuel, vérifier dans la DB que la colonne livreur.type_vehicule_id est exploitable'],
            'FK type_vehicule_id présente, select renseigné si le form est mis à jour.');
        $body .= $this->testBlock('D2-10', 'Bandeau cycle de paiement',
            ['/livreurs/paiements',
             'Vérifier le bandeau "Prochain cycle"',
             'Aller dans /parametre#tab-livreurs',
             'Changer la fréquence en "Quotidien"',
             'Revenir sur /livreurs/paiements → bandeau passe en vert "Aujourd\'hui"',
             'Tester aussi Bimensuel et Mensuel'],
            'Helper Configuration::prochainePaiementLivreur() dynamique sur 4 fréquences.');
        $body .= $this->testBlock('D2-11', 'Paiement livreur multi-tranches + bordereau',
            ['Enregistrer 2 paiements partiels sur une même livraison',
             'Vérifier la mise à jour de reste_a_payer',
             'Télécharger les 2 bordereaux'],
            'Cohérence reste à payer, 2 bordereaux PL-AAAA-XXX.');

        $body .= $this->p('4.6 Module Apporteurs', 'Heading3');
        $body .= $this->testBlock('D2-12', 'Fallback taux_commission_standard',
            ['Créer un apporteur SANS renseigner le pourcentage',
             'En tinker : appeler PaiementController::resolveTauxCommission($apporteur)',
             'Vérifier qu\'il retourne config->taux_commission_standard (ex. 3.00)',
             'Renseigner un pourcentage sur cet apporteur (ex. 5%)',
             'Re-tester → doit retourner 5.00'],
            'Fallback à la config quand pourcentage apporteur est null/0.');
        $body .= $this->testBlock('D2-13', 'Règle : commission due seulement si client a payé',
            ['Créer une commande non payée → vérifier que la commission est "En attente paiement client"',
             'Enregistrer un paiement client',
             'Vérifier le passage de la commission en "Due"'],
            'Statut commission lié au paiement client (helper statutCommissionCalcule()).');

        // ===== JOUR 3 =====
        $body .= $this->p('5. JOUR 3 — Jeudi 13 mai 2026', 'Heading1');
        $body .= $this->p('Récaps, paramétrage, référentiels, automatisations, déploiement', 'Heading2');
        $body .= $this->pMixed([['text' => 'Durée cible : ', 'bold' => true], ['text' => '8 h (matin : validations · après-midi : pré-déploiement + smoke prod simulé)']]);

        $body .= $this->p('5.1 Tableaux de bord consolidés', 'Heading3');
        $body .= $this->testBlock('D3-01', '/gestionnaire/home',
            ['Login admin, ouvrir /gestionnaire/home',
             'Vérifier 4 KPI cards, communauté, top produits, dash-stack-bar',
             'DevTools : aucune erreur, pas de variable JS undefined'],
            'Dashboard premium complet.');
        $body .= $this->testBlock('D3-02', 'Récap Créances + Guide modale',
            ['/recap-creances/tableau-de-bord',
             'Cliquer bouton "Guide d\'utilisation" → modale s\'ouvre',
             'Vérifier les 7 sections du guide',
             'Top 5 débiteurs : vérifier qu\'il n\'y a pas de dump JSON (bug corrigé)'],
            'Modale fonctionnelle, top débiteurs propre.');
        $body .= $this->testBlock('D3-03', 'Récap Dettes + Guide modale',
            ['/recap-dettes/tableau-bord',
             'Cliquer "Guide d\'utilisation" → modale rouge'],
            'Modale dettes opérationnelle.');

        $body .= $this->p('5.2 Paramétrage centralisé', 'Heading3');
        $body .= $this->testBlock('D3-04', '8 onglets de /parametre',
            ['/parametre',
             'Naviguer dans les 8 onglets',
             'Vérifier la persistance après save sur 3 valeurs au choix',
             '$ php artisan tinker  >>> \\App\\Models\\Configuration::first()->toArray()'],
            'Tous les champs persistés, casts (decimal) corrects.');
        $body .= $this->testBlock('D3-05', 'Impact réel des paramètres',
            ['Modifier delai_relance_standard de 7 à 14 (onglet créances)',
             'Aller /clients-terme/relances → la section "À relancer" recalcule avec le nouveau seuil',
             'Remettre à 7'],
            'Le paramètre influence le calcul en temps réel.');
        $body .= $this->testBlock('D3-06', 'Lien vers Agences depuis tab Comptant',
            ['Onglet Comptant/Agence → cliquer le lien vers la liste agences',
             'Vérifier la route show.agences.index (et pas agences.index)'],
            'Pas de RouteNotFoundException.');

        $body .= $this->p('5.3 Référentiels éditables (B2 + B3)', 'Heading3');
        $body .= $this->testBlock('D3-07', 'StatutMetier::badgeFor() avec cache',
            ['$ php artisan tinker',
             '>>> \\App\\Models\\StatutMetier::badgeFor(\'À échoir\', \'creance_terme\')  // bg-info text-white',
             '>>> $s = \\App\\Models\\StatutMetier::pourDomaine(\'creance_terme\')->where(\'libelle\',\'À échoir\')->first()',
             '>>> $s->update([\'badge_class\' => \'bg-primary text-white\'])  // déclenche flush cache',
             '>>> \\App\\Models\\StatutMetier::badgeFor(\'À échoir\', \'creance_terme\')  // bg-primary text-white'],
            'Cache invalidé automatiquement après save (hook booted).');
        $body .= $this->testBlock('D3-08', 'CRUD Statuts métier UI',
            ['/statuts-metier',
             'Filtrer par domaine "comptant"',
             'Créer un statut "En attente livraison" (badge bg-info)',
             'Vérifier l\'aperçu live du badge dans le form',
             'Modifier, désactiver, supprimer'],
            'CRUD complet, aperçu live JS opérationnel.');
        $body .= $this->testBlock('D3-09', 'CRUD Types véhicules + protection FK',
            ['/types-vehicules-livreurs',
             'Créer un type "Pickup 1T"',
             'Modifier sa capacité',
             'Tenter de supprimer un type rattaché à un livreur → vérifier le refus avec compteur'],
            'Protection référentielle active.');
        $body .= $this->testBlock('D3-10', 'Vues migrées vers badgeFor()',
            ['Ouvrir 9 vues : clientTerme/factures, comptant/commandes, fournisseur/enlevements, livreur/livraisons, apporteur/commissions, et les 4 recap*/detail*',
             'Vérifier la présence du helper StatutMetier::badgeFor(',
             'Vérifier l\'absence du match codé en dur (sauf detailComptant qui garde son match local)'],
            'Migration des 9 vues OK, detailComptant intentionnellement préservée.');

        $body .= $this->p('5.4 Automatisations + commandes Artisan', 'Heading3');
        $body .= $this->testBlock('D3-11', 'Schedule Laravel',
            ['$ php artisan schedule:list',
             'Vérifier que gravier:annuler-comptant-perimes est planifié à 02:30 quotidien',
             '$ php artisan schedule:run --no-interaction  // si dans la fenêtre'],
            'Schedule présent. Le cron LWS doit appeler schedule:run chaque minute en prod.');
        $body .= $this->testBlock('D3-12', 'Commande génération PDFs DG/dev',
            ['$ php artisan gravier:rapport-recette-dg',
             '$ php artisan gravier:plan-tests-utilisateur',
             '$ php artisan gravier:plan-tests-dev',
             'Vérifier les 3 fichiers dans storage/app/public/'],
            '3 PDF/DOCX générés sans erreur.');

        $body .= $this->p('5.5 Exports & PDFs', 'Heading3');
        $body .= $this->testBlock('D3-13', 'Reçus de paiement (4 modules)',
            ['Générer un reçu pour chacun : client terme, comptant, fournisseur, livreur, apporteur',
             'Vérifier le numéro auto-incrémenté avec préfixe (RC, RCA, PF, PL, PA)',
             'Vérifier le format A5 + logo + signature'],
            '5 modèles de reçus opérationnels, prefixes corrects.');
        $body .= $this->testBlock('D3-14', 'Grand livre PDF',
            ['/grand-livre/client-ordinaire → cliquer Export PDF',
             'Idem pour à terme, livreur, fournisseur',
             'Vérifier que les soldes sont cohérents'],
            'Grands livres PDF complets.');
        $body .= $this->testBlock('D3-15', 'Exports CSV/Excel/PDF DataTables',
            ['Sur chaque DataTable, tester les boutons Excel + PDF',
             'Vérifier que l\'export contient les bonnes colonnes'],
            'Exports x-export-buttons fonctionnels.');

        $body .= $this->p('5.6 Pré-déploiement', 'Heading3');
        $body .= $this->testBlock('D3-16', 'Caches prod (test local)',
            ['$ php artisan config:cache',
             '$ php artisan route:cache',
             '$ php artisan view:cache',
             'Tester rapidement /login-account, /mon-compte, /parametre',
             'Si OK : $ php artisan config:clear && php artisan route:clear && php artisan view:clear (pour retour dev)'],
            'Application fonctionne avec caches activés (simulation prod).');
        $body .= $this->testBlock('D3-17', 'Permissions + sécurité .env',
            ['Vérifier APP_DEBUG=false en .env.prod (à préparer)',
             'Vérifier APP_KEY non vide',
             'Vérifier MAIL_HOST configuré (SMTP LWS)',
             'Permissions storage/ et bootstrap/cache/ → 775 sur le serveur'],
            'Checklist .env prod prête, permissions documentées.');
        $body .= $this->testBlock('D3-18', 'Backup DB avant déploiement',
            ['$ mysqldump -u root -p graviers > backup_pre_deploy_2026-05-13.sql',
             'Vérifier la taille du dump',
             'Conserver dans un dossier daté'],
            'Backup en sécurité, restaurable si problème.');
        $body .= $this->testBlock('D3-19', 'Migration dry-run',
            ['$ php artisan migrate --pretend  // affiche les SQL sans exécuter',
             'Vérifier qu\'aucune migration destructive n\'est planifiée',
             'Vérifier que toutes les migrations B2/B3 récentes sont incluses'],
            'SQL prévisualisé, aucune surprise destructive.');
        $body .= $this->testBlock('D3-20', 'Test rollback de la dernière migration',
            ['$ php artisan migrate:rollback --step=1  // sur la dernière',
             'Vérifier que l\'app fonctionne toujours (ou attendu fail si feature dépend)',
             '$ php artisan migrate  // remettre'],
            'Procédure rollback maîtrisée. Idem pour batch supérieur si besoin.');

        $body .= $this->p('5.7 Smoke test final local', 'Heading3');
        $body .= $this->testBlock('D3-21', 'Parcours admin complet',
            ['Login admin',
             'Visiter dans l\'ordre : dashboard, /clients-terme/synthese, /comptant/synthese, /fournisseurs/synthese, /livreurs/synthese, /apporteurs/synthese',
             '/recap-creances + /recap-dettes',
             '/parametre',
             '/statuts-metier + /types-vehicules-livreurs',
             '/agences',
             'DevTools : aucune erreur sur le parcours'],
            'Parcours admin complet sans aucune erreur console / réseau.');
        $body .= $this->testBlock('D3-22', 'Parcours client complet',
            ['Login client',
             '/mon-compte, parcourir devis, commandes, paiements',
             'Créer une nouvelle commande, payer',
             'Logout',
             'Login client à terme → vérifier accès à factures à terme'],
            'Parcours B2C complet, redirections correctes selon profil.');
        $body .= $this->testBlock('D3-23', 'Logs Laravel propres',
            ['$ tail -100 storage/logs/laravel.log',
             'Identifier les éventuelles erreurs/warnings résiduels',
             'Si des erreurs : corriger ou documenter avant déploiement'],
            'Logs vides ou contenant uniquement des entrées attendues.');

        // ===== ANNEXES =====
        $body .= $this->p('6. Annexes', 'Heading1');

        $body .= $this->p('6.1 Récapitulatif validation par jour', 'Heading2');
        $body .= $this->bullet('JOUR 1 — Foundation & Auth : ☐ Validé   ☐ Anomalies bloquantes   ☐ Mineures');
        $body .= $this->bullet('JOUR 2 — Modules métier : ☐ Validé   ☐ Anomalies bloquantes   ☐ Mineures');
        $body .= $this->bullet('JOUR 3 — Récaps + Config + Déploiement : ☐ Validé   ☐ Anomalies bloquantes   ☐ Mineures');

        $body .= $this->p('6.2 Commandes Artisan utiles', 'Heading2');
        $body .= $this->bullet('php artisan migrate:status — état des migrations');
        $body .= $this->bullet('php artisan route:list — toutes les routes');
        $body .= $this->bullet('php artisan schedule:list — taches planifiées');
        $body .= $this->bullet('php artisan tinker — REPL pour vérifications DB');
        $body .= $this->bullet('php artisan view:clear — nettoyer le cache Blade');
        $body .= $this->bullet('php artisan gravier:annuler-comptant-perimes --dry-run');
        $body .= $this->bullet('php artisan gravier:rapport-recette-dg');
        $body .= $this->bullet('php artisan gravier:plan-tests-utilisateur');
        $body .= $this->bullet('php artisan gravier:plan-tests-dev');

        $body .= $this->p('6.3 Anomalies bloquantes à corriger avant prod', 'Heading2');
        for ($i = 1; $i <= 8; $i++) {
            $body .= $this->p("#{$i} — ID test : __________ Description : _________________________________________________", '', false, '6B7280');
        }

        $body .= $this->p('6.4 Checklist déploiement LWS', 'Heading2');
        $body .= $this->bullet('☐ Backup DB locale + DB prod (avant)');
        $body .= $this->bullet('☐ git push origin main');
        $body .= $this->bullet('☐ FTP/SSH upload des fichiers modifiés (si pas de git sur prod)');
        $body .= $this->bullet('☐ composer install --no-dev --optimize-autoloader (sur prod)');
        $body .= $this->bullet('☐ php artisan migrate --force (sur prod)');
        $body .= $this->bullet('☐ php artisan config:cache && route:cache && view:cache');
        $body .= $this->bullet('☐ Vérifier .env.prod : APP_DEBUG=false, APP_URL, DB_*, MAIL_*');
        $body .= $this->bullet('☐ Permissions : chmod -R 775 storage/ bootstrap/cache/');
        $body .= $this->bullet('☐ Tâche cron LWS : * * * * * php /chemin/artisan schedule:run');
        $body .= $this->bullet('☐ Tester smoke sur prod : /login-account, /client/login, parcours admin minimal');
        $body .= $this->bullet('☐ Surveiller les logs serveur 24h après déploiement');

        $body .= $this->p('6.5 Décision finale', 'Heading2');
        $body .= $this->bullet('☐ GO DEPLOIEMENT — Tous tests verts ou anomalies non-bloquantes documentées');
        $body .= $this->bullet('☐ NO GO — Anomalies bloquantes à corriger avant');
        $body .= $this->bullet('☐ GO PARTIEL — Déploiement avec périmètre restreint');
        $body .= $this->p('Date prévue de déploiement : ___ / ___ / 2026   Heure : ___ : ___', '', false, '6B7280');
        $body .= $this->p('Signature développeur : __________________________________________________');

        // Wrap final
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
