import 'package:flutter/material.dart';

/// ------------------------------------------------------------------
/// IDENTITÉ VISUELLE — Mon Gravier
/// ------------------------------------------------------------------
///
/// Deux palettes se disputaient l'application : celle-ci (bleu nuit) et celle
/// de `helper/constants.dart` (rose, jaune, dégradés rouges), héritées de deux
/// gabarits différents. Résultat : un rouge pour un numéro de commande, un bleu
/// pour un montant, un vert pour un onglet — sans qu'aucune de ces couleurs ne
/// veuille dire quoi que ce soit.
///
/// La règle est désormais : UNE couleur de marque (bleu nuit), UN accent
/// (ocre — la couleur du matériau vendu), des neutres légèrement bleutés, et
/// trois couleurs qui ne servent QU'À dire un état : succès, alerte, erreur.
///
/// Les anciens noms sont tous conservés : aucun écran n'a besoin d'être
/// réécrit pour bénéficier de la nouvelle palette.

/// LES COULEURS VIENNENT DU LOGO, ET DE LUI SEUL.
///
/// Le logo GRAVIER.COM est entierement bleu : un cercle bleu roi, un anneau
/// navy, un camion indigo, un pare-brise bleu ciel, des roues grises. L'accent
/// OCRE que portait la version precedente ne venait donc de nulle part — il
/// avait ete choisi pour evoquer le materiau, pas la marque.
///
/// Chaque valeur ci-dessous est relevee sur le logo.

/// Indigo de la benne. Actions principales, montants, elements actifs.
const kPrimaryColor = Color(0xFF23326E);

/// Navy de l'anneau exterieur. Barre de titre, bandeaux, aplats.
const kPrimaryDarkColor = Color(0xFF16324F);

/// Bleu roi du cercle. Accent — le seul par ecran.
const kPrimaryMidColor = Color(0xFF1B58A5);

/// Bleu ciel du pare-brise, tres eclairci : fonds de pastilles et d'encarts.
const kPrimarySoftColor = Color(0xFFE6EEF9);

/// L'accent EST le bleu roi du cercle. Il distingue la location de la vente,
/// signale un compteur, marque une action secondaire mise en avant.
const kAccentColor = kPrimaryMidColor;
const kAccentSoftColor = Color(0xFFDCEAF8);

/// Bleu ciel du pare-brise, a pleine intensite : pour un pictogramme pose SUR
/// le navy, ou le bleu roi serait trop sombre pour se detacher.
const kAccentClairColor = Color(0xFF7FB3E8);

/// Conserve pour compatibilite : anciennes teintes du gabarit d'origine.
const kPrimaryLightColor = kPrimarySoftColor;
const kPrimaryGradientColor = LinearGradient(
  begin: Alignment.topLeft,
  end: Alignment.bottomRight,
  colors: [kPrimaryDarkColor, kPrimaryColor],
);

/// Bandeau de tete : les deux bleus du cercle, du navy vers le bleu roi.
const kBandeauGradient = LinearGradient(
  begin: Alignment.topLeft,
  end: Alignment.bottomRight,
  colors: [kPrimaryDarkColor, kPrimaryColor, kPrimaryMidColor],
  stops: [0, 0.55, 1],
);

/// Gris des roues du logo. Fonds de vignettes, separateurs.
const kSecondaryColor = Color(0xFF7C879C);

/// Texte. Un noir pur sur blanc fatigue et durcit inutilement l'ecran ; ce
/// gris tres fonce tire legerement vers le bleu de la marque.
const kTextColor = Color(0xFF131A2B);
const kTextSecondaryColor = Color(0xFF56637A);
const kTextMutedColor = Color(0xFF8794AB);

/// SURFACES.
///
/// Le fond des ecrans etait a deux doigts du blanc : cartes blanches sur fond
/// blanc, barres blanches, tout se confondait et l'application paraissait
/// vide. Le fond prend desormais la teinte du pare-brise du logo, et ce sont
/// les cartes, restees blanches, qui s'en detachent.
const kScaffoldColor = Color(0xFFEDF2F9);
const kSurfaceColor = Colors.white;
const kSurfaceMutedColor = Color(0xFFE4EDF7);
const kBorderColor = Color(0xFFD4E1F0);

/// Contour FRANC, pour les blocs qui doivent se lire comme des blocs :
/// cartes de catalogue, encarts. Le precedent etait trop pale pour se voir
/// sur un ecran un peu lumineux.
const kBorderFortColor = Color(0xFFBACDE6);

/// Pastille posee SUR la barre de titre navy : un voile blanc, pas un aplat
/// gris — un cercle gris clair sur du navy ferait une tache.
const kChipSurAppBar = Color(0x29FFFFFF);

/// Couleurs d'ETAT. Elles ne servent jamais de decoration.
const kSuccessColor = Color(0xFF1E8A5B);
const kSuccessSoftColor = Color(0xFFE4F3EC);
const kWarningColor = Color(0xFFB7791F);
const kWarningSoftColor = Color(0xFFFBF2E2);
const kErrorColor = Color(0xFFC0392B);
const kErrorSoftColor = Color(0xFFFBEAE8);

/// ------------------------------------------------------------------
/// ESPACEMENTS, RAYONS, OMBRES
/// ------------------------------------------------------------------
/// Une seule échelle, pour que deux écrans voisins respirent pareil.
const double kSpaceXs = 4;
const double kSpaceSm = 8;
const double kSpaceMd = 12;
const double kSpaceLg = 16;
const double kSpaceXl = 24;
const double kSpaceXxl = 32;

/// Rayons : francs sans être enfantins.
const double kRadiusSm = 10;
const double kRadiusMd = 14;
const double kRadiusLg = 20;
/// Grand arrondi : reserve aux FEUILLES qui remontent par-dessus un aplat de
/// couleur — la feuille du formulaire de connexion, notamment. A 20, la
/// jonction entre l'en-tete bleu et la feuille blanche restait trop seche.
const double kRadiusXl = 34;

const double kRadiusPill = 100;

/// Ombres : une seule profondeur, très basse. Une carte se détache par son
/// fond et son contour, pas par une ombre portée.
const List<BoxShadow> kShadowSoft = [
  BoxShadow(
    color: Color(0x0F131A2B),
    blurRadius: 16,
    offset: Offset(0, 4),
  ),
];

/// Ombre d'une CARTE de catalogue : plus marquee que `kShadowSoft`, pour que
/// le bloc se decolle nettement du fond — sans pour autant peser. Deux couches :
/// un contact serre juste sous la carte, une diffusion large en dessous.
const List<BoxShadow> kShadowCarte = [
  BoxShadow(
    color: Color(0x14162A4A),
    blurRadius: 3,
    offset: Offset(0, 1),
  ),
  BoxShadow(
    color: Color(0x1A162A4A),
    blurRadius: 14,
    offset: Offset(0, 6),
  ),
];

const List<BoxShadow> kShadowRaised = [
  BoxShadow(
    color: Color(0x14131A2B),
    blurRadius: 24,
    offset: Offset(0, -6),
  ),
];

/// Durées : brèves. Une animation qu'on remarque est une animation trop lente.
const kAnimationDuration = Duration(milliseconds: 180);
const defaultDuration = Duration(milliseconds: 220);

/// ------------------------------------------------------------------
/// TYPOGRAPHIE
/// ------------------------------------------------------------------
/// La hiérarchie doit se lire d'un coup d'œil : un titre d'écran, un titre de
/// section, un corps de texte, une légende. Rien entre les deux.
const headingStyle = TextStyle(
  fontSize: 24,
  fontWeight: FontWeight.w700,
  color: kTextColor,
  height: 1.3,
  letterSpacing: -0.4,
);

const kTitreEcranStyle = TextStyle(
  fontSize: 20,
  fontWeight: FontWeight.w700,
  color: kTextColor,
  height: 1.3,
  letterSpacing: -0.3,
);

const kTitreSectionStyle = TextStyle(
  fontSize: 16,
  fontWeight: FontWeight.w700,
  color: kTextColor,
  height: 1.3,
);

const kCorpsStyle = TextStyle(
  fontSize: 14,
  fontWeight: FontWeight.w400,
  color: kTextColor,
  height: 1.45,
);

const kCorpsSecondaireStyle = TextStyle(
  fontSize: 13,
  fontWeight: FontWeight.w400,
  color: kTextSecondaryColor,
  height: 1.45,
);

const kLegendeStyle = TextStyle(
  fontSize: 12,
  fontWeight: FontWeight.w400,
  color: kTextMutedColor,
  height: 1.35,
);

/// Étiquette en capitales : statut, catégorie, rubrique.
const kEtiquetteStyle = TextStyle(
  fontSize: 11,
  fontWeight: FontWeight.w700,
  height: 1.2,
  letterSpacing: 0.6,
);

/// Montant. Chiffres à chasse fixe : les colonnes s'alignent, et un total qui
/// change ne fait plus danser la ligne.
const kMontantStyle = TextStyle(
  fontSize: 18,
  fontWeight: FontWeight.w700,
  color: kPrimaryColor,
  height: 1.2,
  fontFeatures: [FontFeature.tabularFigures()],
);

const kMontantFortStyle = TextStyle(
  fontSize: 22,
  fontWeight: FontWeight.w700,
  color: kPrimaryColor,
  height: 1.15,
  letterSpacing: -0.4,
  fontFeatures: [FontFeature.tabularFigures()],
);

// Form Error
// Ancienne expression : ^[a-zA-Z0-9.]+@[a-zA-Z0-9]+\.[a-zA-Z]+
// Elle REFUSAIT les adresses contenant un tiret, un underscore ou un « + »
// (jean-luc@..., service_com@...), et n'etait pas ancree en fin de chaine.
final RegExp emailValidatorRegExp =
    RegExp(r"^[\w.+-]+@[\w-]+(\.[\w-]+)+$");
const String kEmailNullError = "Veuillez entrer votre email";
const String kInvalidEmailError = "Veuillez entrer un email valide";
const String kPassNullError = "Veuillez entrer votre mot de passe";
const String kShortPassError = "Votre mot de passe est trop court";
const String kMatchPassError = "Les mot de passe correspondent pas";
const String kNamelNullError = "Veuillez entrer votre nom";
const String kPhoneNumberNullError = "Veuillez entrer votre n° de téléphone";
const String kAddressNullError = "Veuillez entrer votre adresse";

/// Champ de saisie du code OTP : carré, chiffre centré, lisible de loin.
final otpInputDecoration = InputDecoration(
  filled: true,
  fillColor: kSurfaceMutedColor,
  contentPadding: const EdgeInsets.symmetric(vertical: 16),
  border: outlineInputBorder(),
  focusedBorder: outlineInputBorder(couleur: kPrimaryColor, epaisseur: 1.6),
  enabledBorder: outlineInputBorder(),
);

OutlineInputBorder outlineInputBorder({
  Color couleur = kBorderColor,
  double epaisseur = 1,
}) {
  return OutlineInputBorder(
    borderRadius: BorderRadius.circular(kRadiusMd),
    borderSide: BorderSide(color: couleur, width: epaisseur),
  );
}
