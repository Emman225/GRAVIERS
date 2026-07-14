# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Vue d'ensemble du projet

**Mon Gravier App - Apporteur** est une application mobile Flutter destinée aux apporteurs d'affaires de Mon Gravier, un service de livraison de gravier en Côte d'Ivoire. L'application permet aux apporteurs de suivre leurs commissions, gérer leurs demandes de retrait, consulter leurs filleules et suivre les paiements.

- **Environnement** : Flutter 3.13.1 (géré via FVM)
- **Gestion d'état** : GetX (`get` package) + Provider
- **Langue** : Français uniquement
- **API** : Backend hébergé sur `https://apigravier.fneconnect.net` (sous-domaine de fneconnect.net)

## Commandes de développement

### Build & Run
```bash
# Lancer l'app en mode debug (utilise l'API local/test par défaut)
flutter run

# Builder l'APK pour Android
flutter build apk

# Builder pour iOS
flutter build ios

# Nettoyer le cache de build
flutter clean && flutter pub get
```

### Tests
```bash
# Lancer tous les tests
flutter test

# Lancer un test spécifique
flutter test test/path/to/test_file.dart
```

### Qualité du code
```bash
# Analyser le code
flutter analyze

# Générer les icônes de lancement
flutter pub run flutter_launcher_icons
```

### FVM (Flutter Version Manager)
Le projet utilise FVM pour gérer la version Flutter 3.13.1 :
```bash
# Utiliser la version Flutter du projet
fvm flutter run

# Installer la bonne version Flutter
fvm install 3.13.1
fvm use 3.13.1
```

## Architecture & Structure

### Configuration de l'environnement API
L'environnement de l'API est contrôlé dans `lib/globale.dart` :
- `const env = 'local'` → utilise `https://apigravier.fneconnect.net/mon_gravier_apporteur/`
- `const env = 'prod'` → utilise `https://apigravier.fneconnect.net/mon_gravier_apporteur/` (à configurer si différent)

### Pattern de gestion d'état
- **GetX** : Utilisé pour la navigation (`Get.to()`, `Get.back()`) et les utilitaires globaux
- **Provider** : Déclaré en dépendance mais peu utilisé
- **État global** : Session utilisateur et état applicatif stockés dans `lib/globale.dart` (variables globales comme `User user`, `List<Cart> paniers`)

### Navigation
- Utilise des routes nommées définies dans `lib/routes.dart`
- Tous les écrans ont une constante statique `routeName`
- Route initiale : `SignInScreen.routeName`

### Fichiers globaux clés
- **`lib/globale.dart`** : Fichier central d'utilitaires contenant :
  - Configuration URL de l'API (`lienAPI()`)
  - État global (user, panier, demande de livraison)
  - Fonctions helpers (formatage, rognage d'image, vérification de connexion, services de localisation)
  - Utilitaires de dialogues (`confirmationAction`, `fermerApplication`)
  - Formatage de dates et de montants
- **`lib/constants.dart`** : Constantes au niveau de l'application (couleurs, espacements)
- **`lib/helper/constants.dart`** : Constantes UI (couleurs, styles de texte, espacements)
- **`lib/theme.dart`** : Configuration du thème Material

### Organisation des écrans
Les écrans suivent un pattern répertoire-par-fonctionnalité :
```
lib/screens/
├── sign_in/
│   ├── sign_in_screen.dart
│   └── components/
├── home/
│   └── home_screen.dart
├── profile/
│   ├── profile_screen.dart
│   └── components/
├── filleule/
│   ├── filleule_screen.dart
│   └── paiements/
├── commission/
├── demande_retrait/
├── forgot_password/
├── otp/
└── ...
```

### Modèles de données
Situés dans `lib/models/`, les modèles suivent un pattern de sérialisation JSON :
- `User.dart` : Authentification et données de profil utilisateur
- `Cart.dart`, `Product.dart` : Fonctionnalité panier d'achat
- `filleul.dart` : Données des filleules/affiliés
- `demande_livraison.dart` : Modèle de demande de livraison
- `retour_*.dart` : Wrappers de réponses API (ex: `retour_home.dart`, `retour_liste_commission.dart`)

### Pattern d'intégration API
Les appels HTTP suivent ce pattern :
1. Vérifier la connexion avec `verifierConnexion()`
2. Afficher le chargement : `afficherChargement()`
3. POST vers `${lienAPI()}endpoint` avec un body JSON contenant le token `access`
4. Parser la réponse en modèle : `Model.fromJson(jsonDecode(response.body))`
5. Masquer le chargement : `fermerChargement()`
6. Gérer les erreurs avec `EasyLoading.showError()` ou `EasyLoading.showInfo()`

Exemple :
```dart
if (await verifierConnexion()) {
  afficherChargement();
  var param = {"access": user.token.toString()};
  retourHttp = await http.post(
    Uri.parse('${lienAPI()}endpoint'),
    headers: {"Content-Type": "application/json"},
    body: jsonEncode(param)
  ).timeout(const Duration(minutes: 2));

  if (retourHttp.statusCode == 200) {
    var data = Model.fromJson(jsonDecode(retourHttp.body));
    // Traiter le succès
  }
  fermerChargement();
} else {
  EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
}
```

### Composants UI
Composants réutilisables dans `lib/components/` :
- `custom_surfix_icon.dart` : Icônes de suffixe personnalisées pour les champs de saisie
- `form_error.dart` : Affichage des erreurs de validation de formulaire
- `product_card.dart` : Carte d'affichage de produit
- `socal_card.dart` : Composant carte de réseau social

### Flux d'authentification
1. `SignInScreen` → Connexion avec identifiants
2. En cas de succès : Stocker le token utilisateur via `lireOuEcrireDonnee()` (SharedPreferences)
3. Définir l'objet global `user` dans `globale.dart`
4. Naviguer vers `HomeScreen` ou `InitScreen`

### Dépendances clés
- **UI/UX** : `flutter_easyloading`, `carousel_slider`, `buttons_tabbar`
- **État** : `get`, `provider`
- **Localisation** : `geolocator`, `location_picker_flutter_map`
- **Média** : `image_cropper`, `camera_camera`, `file_picker`
- **HTTP** : package `http`
- **Formatage** : `money_formatter`, `intl`
- **Recherche/Filtre** : `amazon_like_filter`, `searchable_listview`, `select_searchable_list`

### Conventions importantes
- Les styles de texte sont centralisés dans `lib/helper/constants.dart` (ex: `black14BoldTextStyle`, `white16BoldTextStyle`)
- Formatage de devises : Utiliser `formaterMontant(double)` depuis `globale.dart`
- Formatage de dates : Utiliser `formaterDate(String)` depuis `globale.dart`
- Couleurs : Utiliser les constantes de `lib/helper/constants.dart` (ex: `kPrimaryColor`, `greenColor`, `redColor`)
- Tous les textes utilisateurs sont en français

### Assets
- Images : `assets/images/`
- Icônes : `assets/icons/`
- Police personnalisée : Muli (regular, bold, light)
