<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Documentation - Intégration FNE GRAVIERS</title>
    <style>
        @page { size: A4; margin: 18mm 16mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #222;
        }
        h1 { font-size: 18pt; color: #1c57a3; border-bottom: 3px solid #1c57a3; padding-bottom: 6px; margin-bottom: 4px; }
        h2 { font-size: 13pt; color: #1c57a3; border-bottom: 1px solid #bbb; padding-bottom: 3px; margin-top: 18px; margin-bottom: 8px; }
        h3 { font-size: 11pt; color: #0a5a3a; margin-top: 12px; margin-bottom: 5px; }
        p  { margin: 4px 0 8px 0; text-align: justify; }
        ul, ol { margin: 4px 0 8px 18px; }
        li { margin-bottom: 3px; }
        code, .code {
            font-family: Consolas, "Courier New", monospace;
            font-size: 9pt;
            background: #f4f4f4;
            padding: 1px 4px;
            border-radius: 2px;
        }
        pre {
            background: #1e1e1e;
            color: #e8e8e8;
            padding: 8px 10px;
            border-radius: 3px;
            font-family: Consolas, "Courier New", monospace;
            font-size: 8.5pt;
            line-height: 1.3;
            white-space: pre-wrap;
            page-break-inside: avoid;
        }
        pre .k  { color: #569cd6; }
        pre .s  { color: #ce9178; }
        pre .n  { color: #b5cea8; }
        pre .c  { color: #6a9955; font-style: italic; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px 0;
            font-size: 9pt;
        }
        th, td {
            border: 1px solid #aaa;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #e8eef7; color: #1c57a3; }
        .meta {
            font-size: 8.5pt;
            color: #666;
            margin-top: 0;
            margin-bottom: 14px;
        }
        .badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 9px;
            font-size: 8pt;
            font-weight: bold;
            color: #fff;
        }
        .badge-green  { background: #2a9d4d; }
        .badge-red    { background: #c0392b; }
        .badge-gray   { background: #7f8c8d; }
        .badge-orange { background: #e67e22; }
        .note {
            background: #fff8d6;
            border-left: 4px solid #e6b800;
            padding: 6px 10px;
            margin: 8px 0;
            font-size: 9pt;
        }
        .ok {
            background: #e6f4ea;
            border-left: 4px solid #2a9d4d;
            padding: 6px 10px;
            margin: 8px 0;
            font-size: 9pt;
        }
        .warn {
            background: #fdecea;
            border-left: 4px solid #c0392b;
            padding: 6px 10px;
            margin: 8px 0;
            font-size: 9pt;
        }
        .footer-page {
            text-align: center;
            font-size: 8pt;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 4px;
            margin-top: 16px;
        }
        .toc { font-size: 9pt; }
        .toc li { margin-bottom: 2px; }
        .small { font-size: 8.5pt; color: #555; }
    </style>
</head>
<body>

<h1>Intégration FNE - GRAVIERS.COM</h1>
<p class="meta">
    Direction Générale des Impôts (Côte d'Ivoire) — Facture Normalisée Electronique<br>
    Application : <strong>graviers.fneconnect.net</strong> (Dalakoun SARL)<br>
    Document technique - Version 1.0 - {{ now()->format('d/m/Y') }}
</p>

<h2>1. Contexte</h2>
<p>
    La loi de finances 2025 institue l'obligation de délivrance de la
    <strong>Facture Normalisée Electronique (FNE)</strong>. Toute facture émise
    par GRAVIERS doit désormais être certifiée par la plateforme FNE de la DGI
    via son API REST, qui appose une signature électronique en trois éléments :
</p>
<ul>
    <li>un <strong>QR Code</strong> renvoyant vers la page de vérification publique ;</li>
    <li>le <strong>visuel FNE</strong> officiel ;</li>
    <li>une <strong>numérotation</strong> en série annuelle ininterrompue.</li>
</ul>
<p>
    Cette intégration concerne le tableau « Les factures » de la page
    <code>/orders-be/{numero}</code> (sidebar « Commandes traitées » → onglet
    « Bon d'enlèvement / Factures »).
</p>

<h2>2. Architecture mise en place</h2>

<h3>2.1 Vue d'ensemble</h3>
<pre>
[ Utilisateur GRAVIERS ]
        │  clic « Générer une facture » sur /orders-be/{numero}
        ▼
[ OrdersController::genererFacture() ]
        │  1. Crée la Facture en local (numero, montant, ...)
        │  2. Lie les enlèvements sélectionnés
        ▼
[ FneService::signInvoice($facture) ]
        │  1. Construit le payload JSON (buildSalePayload)
        │  2. POST $url/external/invoices/sign  (Bearer Token DGI)
        ▼
[ Plateforme FNE - DGI ]  →  réponse 200 :
        { "ncc", "reference", "token", "balance_sticker", "invoice": {...} }
        ▼
[ FneService ]  →  Met à jour la facture :
        fne_reference, fne_token, fne_invoice_id, fne_balance_sticker,
        fne_status='certified', fne_certified_at, fne_response_payload
        ▼
[ BECommande.blade ] → badge « Certifiée DGI » + lien « Vérifier »
[ PDF facture ]      → mention « CERTIFIÉE PAR LA DGI - FNE » + QR officiel
</pre>

<h3>2.2 Fichiers livrés / modifiés</h3>
<table>
    <thead>
        <tr><th>Fichier</th><th>Type</th><th>Rôle</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>config/fne.php</code></td>
            <td>Nouveau</td>
            <td>Centralise les paramètres FNE (URL, clé API, defaults).</td>
        </tr>
        <tr>
            <td><code>.env / .env.example</code></td>
            <td>Modifié</td>
            <td>Variables <code>FNE_*</code> (désactivées par défaut).</td>
        </tr>
        <tr>
            <td><code>database/migrations/2026_04_29_120000_<br>add_fne_certification_fields_to_facture_table.php</code></td>
            <td>Nouveau</td>
            <td>Ajoute 12 colonnes FNE à la table <code>facture</code>.</td>
        </tr>
        <tr>
            <td><code>app/Models/Facture.php</code></td>
            <td>Modifié</td>
            <td>Nouveaux fillables + casts + helper <code>isCertifiedFne()</code>.</td>
        </tr>
        <tr>
            <td><code>app/Services/FneService.php</code></td>
            <td>Étendu</td>
            <td>Ajout des méthodes HTTP <code>signInvoice()</code> et <code>refundInvoice()</code>.</td>
        </tr>
        <tr>
            <td><code>app/Http/Controllers/OrdersController.php</code></td>
            <td>Modifié</td>
            <td>Appel auto à FNE après création + nouvelle action <code>recertifierFacture()</code>.</td>
        </tr>
        <tr>
            <td><code>routes/web.php</code></td>
            <td>Modifié</td>
            <td>Route <code>POST /recertifier-facture/{facture}</code>.</td>
        </tr>
        <tr>
            <td><code>resources/views/orders/BECommande.blade.php</code></td>
            <td>Modifié</td>
            <td>Tableau « Les factures » : colonnes N° FNE + Statut + actions.</td>
        </tr>
        <tr>
            <td><code>resources/views/document/layouts/fne_base.blade.php</code></td>
            <td>Modifié</td>
            <td>Mention « CERTIFIÉE PAR LA DGI » sur le PDF certifié.</td>
        </tr>
    </tbody>
</table>

<h2>3. Schéma BDD - colonnes ajoutées</h2>
<table>
    <thead>
        <tr><th>Colonne</th><th>Type</th><th>Description</th></tr>
    </thead>
    <tbody>
        <tr><td><code>fne_invoice_id</code></td><td>varchar(64)</td><td>UUID renvoyé par la plateforme FNE.</td></tr>
        <tr><td><code>fne_reference</code></td><td>varchar(60)</td><td>Numéro officiel DGI (ex. <code>9606123E25000000019</code>).</td></tr>
        <tr><td><code>fne_token</code></td><td>varchar(255)</td><td>URL de vérification (sert de contenu QR Code).</td></tr>
        <tr><td><code>fne_balance_sticker</code></td><td>integer</td><td>Stock de stickers restants côté DGI.</td></tr>
        <tr><td><code>fne_warning</code></td><td>boolean</td><td>Alerte stock bas FNE.</td></tr>
        <tr><td><code>fne_template</code></td><td>varchar(10)</td><td>B2C / B2B / B2G / B2F.</td></tr>
        <tr><td><code>fne_payment_method</code></td><td>varchar(30)</td><td>cash, card, mobile-money, …</td></tr>
        <tr><td><code>fne_status</code></td><td>varchar(20)</td><td>pending / certified / failed / disabled.</td></tr>
        <tr><td><code>fne_certified_at</code></td><td>timestamp</td><td>Date/heure de la certification.</td></tr>
        <tr><td><code>fne_error_message</code></td><td>text</td><td>Dernier message d'erreur DGI.</td></tr>
        <tr><td><code>fne_request_payload</code></td><td>json</td><td>Payload envoyé (audit DGI).</td></tr>
        <tr><td><code>fne_response_payload</code></td><td>json</td><td>Réponse complète FNE (audit DGI).</td></tr>
    </tbody>
</table>

<h2>4. Flux fonctionnel</h2>

<h3>4.1 Création d'une facture (cas nominal)</h3>
<ol>
    <li>L'utilisateur ouvre <code>/orders-be/{numero}</code>, coche les enlèvements et clique <strong>« Generer une facture »</strong>.</li>
    <li>Le contrôleur <code>OrdersController::genererFacture()</code> crée la <code>Facture</code> locale et lie les <code>Enlevement</code>.</li>
    <li><code>FneService::signInvoice($facture)</code> est appelé automatiquement :
        <ul>
            <li>Construction du JSON conforme à la doc DGI à partir de la commande, du client et des enlèvements.</li>
            <li><code>POST {{ '$' }}url/external/invoices/sign</code> avec en-tête <code>Authorization: Bearer &lt;FNE_API_KEY&gt;</code>.</li>
            <li>Si réponse 200 → la facture est mise à jour avec la référence officielle, le token QR et le sticker restant.</li>
        </ul>
    </li>
    <li>L'utilisateur revient sur la page : la facture apparaît avec le badge
        <span class="badge badge-green">Certifiée DGI</span>, sa référence officielle et un bouton <em>Vérifier</em>.
    </li>
</ol>

<h3>4.2 Robustesse / mode dégradé</h3>
<div class="note">
    Tant que la DGI n'a pas fourni la clé API (<code>FNE_API_KEY</code>) et tant
    que <code>FNE_ENABLED=false</code>, aucun appel HTTP n'est tenté. La facture
    est créée localement avec <code>fne_status=disabled</code> et un message
    informe l'utilisateur. Le bouton <strong>« Certifier »</strong> sur chaque
    facture permet de relancer la certification une fois les credentials
    disponibles, sans intervention de développeur.
</div>

<h3>4.3 Statuts possibles</h3>
<table>
    <thead><tr><th>Statut</th><th>Affichage</th><th>Signification</th></tr></thead>
    <tbody>
        <tr><td><code>certified</code></td><td><span class="badge badge-green">Certifiée DGI</span></td><td>Certifiée avec succès. Référence officielle disponible.</td></tr>
        <tr><td><code>failed</code></td><td><span class="badge badge-red">Échec FNE</span></td><td>Erreur DGI (4xx/5xx). Bouton « Réessayer » disponible.</td></tr>
        <tr><td><code>disabled</code></td><td><span class="badge badge-gray">Non certifiée</span></td><td>Module FNE désactivé. Bouton « Certifier ».</td></tr>
        <tr><td><code>pending</code></td><td><span class="badge badge-orange">En attente</span></td><td>Initialisation, pas encore traitée.</td></tr>
    </tbody>
</table>

<h2>5. Mapping des données GRAVIERS → FNE</h2>

<h3>5.1 Template (type de facturation)</h3>
<p><code>FneService::determineTemplate($client)</code> :</p>
<ul>
    <li>Client a un <code>ncc_clt</code> renseigné → <strong>B2B</strong></li>
    <li><code>type_client</code> = particulier/individuel → <strong>B2C</strong></li>
    <li><code>type_client</code> = etat/gouvernement/institution → <strong>B2G</strong></li>
    <li><code>type_client</code> = international/etranger → <strong>B2F</strong></li>
    <li>Sinon → valeur de <code>FNE_DEFAULT_TEMPLATE</code></li>
</ul>

<h3>5.2 Mode de paiement</h3>
<p><code>FneService::mapPaymentMethod($libelle)</code> normalise le libellé local :</p>
<table>
    <thead><tr><th>Libellé local (contient)</th><th>Valeur FNE</th></tr></thead>
    <tbody>
        <tr><td>espèce / cash</td><td><code>cash</code></td></tr>
        <tr><td>carte / cb</td><td><code>card</code></td></tr>
        <tr><td>chèque / check</td><td><code>check</code></td></tr>
        <tr><td>mobile / momo / wave / orange money / mtn</td><td><code>mobile-money</code></td></tr>
        <tr><td>virement / transfer</td><td><code>transfer</code></td></tr>
        <tr><td>terme / crédit</td><td><code>deferred</code></td></tr>
    </tbody>
</table>

<h3>5.3 TVA</h3>
<table>
    <thead><tr><th>Code FNE</th><th>Taux</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>TVA</code></td><td>18%</td><td>Taux normal</td></tr>
        <tr><td><code>TVAB</code></td><td>9%</td><td>Taux réduit</td></tr>
        <tr><td><code>TVAC</code></td><td>0%</td><td>Exonération conventionnelle</td></tr>
        <tr><td><code>TVAD</code></td><td>0%</td><td>Exonération légale (TEE / RME)</td></tr>
    </tbody>
</table>

<h2>6. Exemple d'appel API</h2>

<h3>6.1 Requête envoyée</h3>
<pre>POST http://54.247.95.108/ws/external/invoices/sign
Content-Type: application/json
Authorization: Bearer <span class="s">kAF01gEM40r1Uz5WLJn5lxAnGMwVjCME</span>

{
  "invoiceType": "sale",
  "paymentMethod": "mobile-money",
  "template": "B2B",
  "isRne": false,
  "clientNcc": "9502363N",
  "clientCompanyName": "ENTREPRISE EXEMPLE",
  "clientPhone": "0709080765",
  "clientEmail": "info@exemple.ci",
  "pointOfSale": "PDV-01",
  "establishment": "GRAVIERS",
  "items": [
    {
      "taxes": ["TVA"],
      "reference": "GRA-001",
      "description": "Sable lavé",
      "quantity": 30,
      "amount": 20000,
      "discount": 0,
      "measurementUnit": "tonne"
    }
  ],
  "discount": 0
}</pre>

<h3>6.2 Réponse de succès (HTTP 200)</h3>
<pre>{
  "ncc": "9606123E",
  "reference": "9606123E25000000019",
  "token": "http://54.247.95.108/fr/verification/019465c1-3f61-766c-9652-706e32dfb436",
  "warning": false,
  "balance_sticker": 179,
  "invoice": { "id": "e2b2d8da-...", ... }
}</pre>

<h3>6.3 Codes d'erreur gérés</h3>
<table>
    <thead><tr><th>Code</th><th>Cause</th><th>Action côté GRAVIERS</th></tr></thead>
    <tbody>
        <tr><td>200/201</td><td>Succès</td><td>Mise à jour de la facture (<code>certified</code>).</td></tr>
        <tr><td>400</td><td>Requête invalide (point de vente, données…)</td><td>Statut <code>failed</code>, message stocké.</td></tr>
        <tr><td>401</td><td>Clé API invalide</td><td>Statut <code>failed</code>, alerte admin.</td></tr>
        <tr><td>500</td><td>Endpoint indisponible</td><td>Statut <code>failed</code>, retry possible.</td></tr>
    </tbody>
</table>

<h2>7. Configuration - paramètres .env</h2>
<pre><span class="c"># Activation globale (false = aucun appel HTTP)</span>
FNE_ENABLED=false

<span class="c"># URL de l'API FNE (test fournie par DGI ; prod après validation)</span>
FNE_BASE_URL=http://54.247.95.108/ws

<span class="c"># Clé API Bearer Token (à récupérer dans l'espace FNE de l'entreprise)</span>
FNE_API_KEY=

<span class="c"># Timeouts et tentatives</span>
FNE_TIMEOUT=20
FNE_RETRY_TIMES=1
FNE_RETRY_SLEEP=500

<span class="c"># Valeurs par défaut</span>
FNE_DEFAULT_TEMPLATE=B2C
FNE_DEFAULT_PAYMENT_METHOD=cash
FNE_DEFAULT_TAX=TVA
FNE_POINT_OF_SALE=PDV-01
FNE_ESTABLISHMENT=GRAVIERS
FNE_COMMERCIAL_MESSAGE=
FNE_FOOTER=

<span class="c"># Si true : la facture est rejetée quand FNE est down</span>
FNE_BLOCK_ON_FAILURE=false
FNE_LOG_CHANNEL=stack</pre>

<h2>8. Procédure d'activation (à suivre par le directeur)</h2>
<ol>
    <li><strong>Inscription</strong> de Dalakoun SARL sur la plateforme FNE de
        test : <code>http://54.247.95.108</code></li>
    <li><strong>Configuration</strong> de l'environnement test côté DGI
        (point de vente, établissement, NCC).</li>
    <li><strong>Tests</strong> de génération de factures (vente / avoir /
        bordereau) depuis GRAVIERS en pointant sur l'URL de test. <em>Le code
        est déjà prêt : il suffit de remplir <code>FNE_API_KEY</code>.</em></li>
    <li><strong>Transmission</strong> des spécimens de factures à la DGI :
        <code>support.fne@dgi.gouv.ci</code></li>
    <li><strong>Validation</strong> par la DGI → réception de l'URL de
        production et de la clé API définitive.</li>
    <li><strong>Mise en production</strong> : modifier <code>.env</code> sur le
        serveur GRAVIERS :
        <pre>FNE_ENABLED=true
FNE_BASE_URL=&lt;URL prod fournie par la DGI&gt;
FNE_API_KEY=&lt;clé fournie par la DGI&gt;
FNE_POINT_OF_SALE=&lt;PDV configuré côté DGI&gt;
FNE_ESTABLISHMENT=&lt;Etablissement&gt;</pre>
        Puis <code>php artisan migrate</code> et <code>php artisan config:clear</code>.
    </li>
</ol>

<div class="ok">
    <strong>À partir de cet instant</strong>, chaque clic sur « Générer une
    facture » déclenchera automatiquement la certification DGI. Les factures
    déjà créées en mode dégradé pourront être recertifiées d'un clic via le
    bouton « Certifier » présent sur chaque ligne du tableau « Les factures ».
</div>

<h2>9. Tests à effectuer après activation</h2>
<ol>
    <li><strong>Test connexion</strong> : générer une facture et vérifier le
        badge <span class="badge badge-green">Certifiée DGI</span>.</li>
    <li><strong>Test QR Code</strong> : cliquer « Vérifier » → la page DGI
        s'ouvre avec les détails de la facture.</li>
    <li><strong>Test PDF</strong> : télécharger la facture, vérifier la mention
        « CERTIFIÉE PAR LA DGI - FNE » et le QR Code officiel.</li>
    <li><strong>Test résilience</strong> : couper temporairement
        <code>FNE_ENABLED=false</code>, vérifier que la facture reste créée
        avec le statut <em>Non certifiée</em> et le bouton « Certifier ».</li>
    <li><strong>Test recertification</strong> : remettre <code>FNE_ENABLED=true</code>
        et cliquer « Certifier » sur la facture précédente → elle doit passer
        en <em>Certifiée DGI</em>.</li>
    <li><strong>Test stock stickers</strong> : surveiller la valeur
        <code>fne_balance_sticker</code> et le drapeau <code>fne_warning</code>
        après quelques factures (la DGI alerte quand le stock est bas).</li>
</ol>

<h2>10. Support et maintenance</h2>
<ul>
    <li><strong>Logs</strong> : tous les appels FNE (succès / erreurs / payloads)
        sont écrits dans <code>storage/logs/laravel.log</code> via le canal
        <code>FNE_LOG_CHANNEL</code>.</li>
    <li><strong>Audit DGI</strong> : les colonnes <code>fne_request_payload</code>
        et <code>fne_response_payload</code> conservent l'intégralité de chaque
        échange pour la DGI en cas de contrôle.</li>
    <li><strong>Contact DGI</strong> : <code>support.fne@dgi.gouv.ci</code></li>
    <li><strong>Contact technique</strong> : équipe développement GRAVIERS.</li>
</ul>

<div class="footer-page">
    Dalakoun SARL — GRAVIERS.COM — Documentation d'intégration FNE
    — généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
