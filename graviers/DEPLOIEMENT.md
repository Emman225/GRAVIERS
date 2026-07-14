# Guide de déploiement en production — GRAVIERCI

Hébergement : cPanel + accès **SSH/terminal** (prompt type `cp...@web41`).
Application : Laravel (PHP 8.3).

> ⚠️ Si une base/installation de prod existe déjà : **sauvegardez** d'abord
> (base + fichiers) avant de remplacer quoi que ce soit.

---

## Vue d'ensemble (les 7 grandes étapes)
1. Mettre le **code** sur le serveur
2. Installer les **dépendances** (composer)
3. Configurer le **`.env`** de production
4. Créer + remplir la **base de données**
5. **Lien storage** + permissions
6. Pointer le **domaine** sur le dossier `public/`
7. **Caches** + vérifications

---

## 1. Mettre le code sur le serveur

Placez le projet dans votre espace, par ex. `/home/cp2751659/gravierci`
(PAS directement dans `public_html` — voir étape 6).

Méthodes au choix :
- **Git** (recommandé) : `git clone <votre-repo> gravierci`
- **ZIP** : compresser le dossier `graviers` en local → l'uploader via le
  Gestionnaire de fichiers cPanel → Extraire.
- **FTP** (FileZilla) : envoyer tout le dossier.

> Ne PAS envoyer : `vendor/`, `node_modules/`, `.env` local (on le refait), `/storage/logs/*`.

## 2. Installer les dépendances
En SSH, dans le dossier du projet :
```bash
cd /home/cp2751659/gravierci
composer install --no-dev --optimize-autoloader
```
(Si `composer` n'est pas trouvé : `php composer.phar install --no-dev --optimize-autoloader`.)

## 3. Configurer le `.env` de production
Copier puis éditer :
```bash
cp .env.example .env     # si pas de .env ; sinon éditez le .env existant
php artisan key:generate # uniquement si APP_KEY est vide
```
Valeurs OBLIGATOIRES :
```
APP_NAME="GRAVIERCI"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gravierci.com        # votre vrai domaine, en https

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cp2751659_gravier        # créée à l'étape 4
DB_USERNAME=cp2751659_xxxx
DB_PASSWORD=********                  # mot de passe fort
```
À vérifier aussi : `MAIL_*` (emails), `PAYSECURE_*` (paiement en ligne), `FNE_*` (voir étape 7).

## 4. Base de données

**4.1 Créer la base** dans cPanel → « MySQL Databases » :
- créer une base (ex. `cp2751659_gravier`)
- créer un utilisateur + mot de passe
- **attribuer l'utilisateur à la base** avec TOUS les privilèges
- reporter ces infos dans le `.env` (étape 3)

**4.2 Importer les données** — UN seul fichier prêt : **`gravierci_production.sql`**
(déjà nettoyé : aucune donnée de test, `DEFINER` retiré).

- Option phpMyAdmin : cPanel → phpMyAdmin → sélectionner la base → onglet
  **Importer** → choisir `gravierci_production.sql` → Exécuter.
- Option terminal :
  ```bash
  mysql -u cp2751659_xxxx -p cp2751659_gravier < gravierci_production.sql
  ```

> Ce fichier contient déjà : référentiel + Configuration + admin **imlod** (mdp `2024`)
> + 1 fournisseur (`fournisseur1`, mdp `2024`) + catalogue (19 produits + 6 locations).
> **Aucune** commande/donnée de test.
>
> ⚠️ NE PAS lancer `migrate:fresh` (migrations cassées depuis zéro) ni
> `db:seed` sans `--class=ProductionSeeder` (le seeder par défaut = fausses données).

## 5. Lien storage + permissions + images
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```
- **Images du catalogue** : uploadez le dossier `storage/app/public/productsImage`
  (sinon les photos produits ne s'affichent pas).
- Si `storage:link` est bloqué, ouvrez dans le navigateur : `https://gravierci.com/storage-link`.

## 6. Pointer le domaine sur `public/`
Le dossier exposé au web doit être **`gravierci/public`**, jamais la racine du projet.
Sur cPanel → « Domaines » (ou Sous-domaines) → **Document Root** = `gravierci/public`.

(Alternative si vous ne pouvez pas changer le Document Root : mettre le contenu de
`public/` dans `public_html` et adapter les chemins dans `public_html/index.php`
vers `../gravierci`. Préférez la 1re méthode.)

## 7. Caches + vérification
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status     # doit afficher "Ran" partout, rien à exécuter
```
> À REFAIRE (`config:cache` / `route:cache`) après chaque modif du `.env` ou des routes.

---

## 8. Tests après mise en ligne
- Ouvrir `https://gravierci.com` → les 19 produits s'affichent (avec photos).
- Se connecter **imlod / 2024**.
- Faire une commande de test → paiement → vérifier email + callback PaySecure.

## 9. À finaliser DANS l'application
- [ ] **Paramètres → fiche entreprise** : raison sociale, NCC, RCCM, régime
      d'imposition, centre des impôts, CNPS, capital, adresse, emails trésorier/marketing.
      (Vides après import — **obligatoires pour les factures/FNE**.)
- [ ] **Changer les mots de passe** par défaut : `imlod` et `fournisseur1` (créés avec `2024`).
- [ ] Créer vos vrais comptes : fournisseurs, livreurs, apporteurs.
- [ ] **FNE / DGI** : `FNE_ENABLED=true` + `FNE_API_KEY=...` quand la DGI vous l'aura fournie.

## 10. Sécurité
- [ ] `.env` jamais versionné dans git.
- [ ] HTTPS (SSL) actif + redirection http → https.
- [ ] `APP_DEBUG=false` (déjà à l'étape 3).
- [ ] Sauvegarde automatique de la base activée.

---

## Fichiers de déploiement (à transférer)
- Le projet (`gravierci`)
- **`gravierci_production.sql`** (base propre, à importer)
- Une fois en place et vérifié, `gravierci_production.sql` peut être supprimé du serveur.
