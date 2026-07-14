# Guide de déploiement — API `apigravier` + applications mobiles

Hébergement : cPanel + SSH. Domaine API : **https://apigravier.fneconnect.net**

> ⚠️ L'API **partage la même base de données** que l'application web (`graviers`).
> Donc sur l'API : **AUCUNE migration, AUCUN seed**. Elle se connecte simplement
> à la base déjà créée par le déploiement du web.

---

## PARTIE 1 — Déployer l'API

### 1. Mettre le code sur le serveur
Placez `apigravier` dans un dossier dédié, ex. `/home/cp2751659/apigravier`
(PAS dans `public_html`).

### 2. Dépendances
```bash
cd /home/cp2751659/apigravier
composer install --no-dev --optimize-autoloader
```

### 3. Configurer le `.env`
```
APP_NAME="GRAVIERCI API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://apigravier.fneconnect.net
PAIEMENT_BASE_URL=https://apigravier.fneconnect.net

# ★ MÊME base que l'app web (base PARTAGÉE) :
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cp2751659_gravier        # nom réel de la base de prod
DB_USERNAME=cp2751659_xxxx
DB_PASSWORD=********

# PaySecure (paiement mobile money)
PAYSECURE_URL=https://rest-airtime.paysecurehub.com/api/payhub-ws/build-away
PAYSECURE_API_KEY=...
PAYSECURE_MERCHANT_ID=...
PAYSECURE_STATUS_URL=...
```
```bash
php artisan key:generate     # uniquement si APP_KEY est vide (l'API a sa propre clé)
```

### 4. Storage + permissions
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### 5. Sous-domaine → dossier public
cPanel → Sous-domaines : créer `apigravier.fneconnect.net`
avec **Document Root = `apigravier/public`** (jamais la racine du projet).

### 6. ⚠️ À NE PAS FAIRE
- ❌ `php artisan migrate` / `migrate:fresh`
- ❌ `php artisan db:seed`
(La base est déjà construite par le déploiement du web. La toucher casserait les données.)

### 7. Caches
```bash
php artisan config:cache
php artisan route:cache      # si erreur de doublon de nom → `php artisan route:clear` et on s'en passe (optionnel)
php artisan view:cache
```
> À refaire après chaque modif du `.env` ou des routes.

### 8. Vérifier l'API + le webhook de paiement
```bash
curl -X POST https://apigravier.fneconnect.net/mon_gravier/callBackPaiement -d "code=999&codePaiement=TEST"
```
→ Doit RÉPONDRE (pas 404/403/500). C'est l'URL que PaySecure appelle pour
confirmer un paiement mobile money. Si elle est injoignable, les paiements
resteraient « en attente » même après règlement.

---

## PARTIE 2 — Pointer les 3 applications mobiles sur l'API

Les URLs de production sont **déjà** dans le code (fichier `lib/globale.dart`).
Il suffit de basculer en mode prod puis de recompiler, pour chaque app.

| Application | Dossier | Préfixe API | URL de prod |
|---|---|---|---|
| Client    | `mon_gravier_mobile_client`    | `mon_gravier`           | https://apigravier.fneconnect.net/mon_gravier/ |
| Livreur   | `mon_gravier_mobile_livreur`   | `mon_gravier_livreur`   | https://apigravier.fneconnect.net/mon_gravier_livreur/ |
| Apporteur | `mon_gravier_mobile_apporteur` | `mon_gravier_apporteur` | https://apigravier.fneconnect.net/mon_gravier_apporteur/ |

### Pour CHAQUE app :
1. Ouvrir `lib/globale.dart` et passer l'environnement en production :
   ```dart
   const env = 'local';     // AVANT
   const env = 'prod';      // APRÈS
   ```
2. Compiler la version release :
   ```bash
   flutter pub get
   flutter build apk --release            # APK (installation directe)
   # ou pour le Play Store :
   flutter build appbundle --release
   ```
3. Installer l'APK (ou publier le `.aab` sur le Play Store).

> Le deeplink `gravier://` (retour après paiement) doit être déclaré dans le
> `AndroidManifest.xml` de chaque app (déjà le cas s'il fonctionnait en test).

---

## Points de vigilance — base PARTAGÉE web + API
- **Mêmes ids de référence** indispensables (le web ET l'API s'appuient dessus) :
  `type_user` = 1..6 (client = 4, fournisseur = 5…), modes de paiement 1..10
  (1 = En Agence). Déjà garantis par `ProductionSeeder` lors du déploiement web.
- Web et API ont **chacun leur `.env`**, mais **les mêmes `DB_*`**.
- Un paiement mobile money devient « effectif » **uniquement** quand PaySecure
  appelle `…/mon_gravier/callBackPaiement` (code=200) → `paiement.statut = 1`.
  D'où l'importance de l'étape 8.

## Flux paiement mobile money (rappel)
```
App mobile → WebView PaySecure → client paie (Orange/MTN/Wave)
   ↓
PaySecure → POST /mon_gravier/callBackPaiement (code=200)   ← devient EFFECTIF ici
   ↓  (paiement.statut=1, ligne_paiement.statut=1, facture.statut=1, points)
PaySecure → retour app (deeplink gravier://) → écran reçu
```
Filet de sécurité : un gestionnaire peut confirmer manuellement un paiement
si un webhook se perd.

