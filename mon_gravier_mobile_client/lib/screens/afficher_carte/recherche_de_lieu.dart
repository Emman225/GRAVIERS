/// RECHERCHE DE LIEU — LES MÊMES RÈGLES QUE LE SITE, EN UN SEUL ENDROIT.
///
/// Le site réunit sa configuration dans
/// `graviers/public/frontend/assets/leaflet/recherche-lieu.js`. Ce fichier en
/// est le pendant : les deux canaux doivent interroger Nominatim avec les
/// mêmes paramètres et présenter les résultats de la même façon. Une recherche
/// qui ne donne pas les mêmes lieux d'un canal à l'autre est une source de
/// confusion pour le client comme pour le gestionnaire.
///
/// Ces règles vivaient à l'intérieur de l'écran de carte, et l'essai les
/// RECOPIAIT pour les vérifier. Un essai qui recopie ce qu'il contrôle ne
/// contrôle rien : l'écran pouvait changer sans que rien ne le signale. Elles
/// sont donc sorties ici, et l'essai les importe.
library;

/// Paramètres envoyés à Nominatim, identiques à ceux du site.
///
/// `countrycodes` FILTRE réellement les résultats, et c'est voulu :
/// l'entreprise livre en Côte d'Ivoire. Sans ce filtre, « Riviera » renvoie le
/// Texas, la Suisse et la Provence, et « Gare routière » renvoie Auxerre et
/// Ghardaïa — le bon résultat se perd dans une liste mondiale.
const Map<String, String> parametresNominatim = {
  'countrycodes': 'ci',
  'accept-language': 'fr',
  'addressdetails': '1',
  'limit': '8',
};

/// Trois caractères avant d'interroger le service : en deçà, la réponse n'a
/// aucune valeur et la requête est gaspillée.
const int minimumCaracteresRecherche = 3;

/// 400 ms de silence avant d'envoyer : on ne part pas à chaque touche frappée.
/// Nominatim bride le débit et bloque les applications trop bavardes.
const Duration delaiAvantAppelRecherche = Duration(milliseconds: 400);

/// Contact réel de l'entreprise : Nominatim exige un identifiant valide et
/// bloque les applications qui n'en donnent pas. L'ancien — une adresse en
/// `mongravier.com`, domaine qui n'existe pas — n'en était pas un.
const String identifiantAppelNominatim = 'GravierCom/1.0 (info@fneconnect.net)';

/// Construit l'interrogation du service pour un terme cherché.
Uri uriRechercheLieu(String terme) => Uri.https(
      'nominatim.openstreetmap.org',
      '/search',
      {
        'format': 'json',
        'q': terme,
        ...parametresNominatim,
      },
    );

/// Un libellé de suggestion, coupé en deux : le lieu, puis sa localisation.
class LibelleLieu {
  const LibelleLieu(this.nom, this.contexte);

  /// Ce que le client reconnaît : « Riviéra 3 », « Marché de Cocody ».
  final String nom;

  /// Ce qui le situe : « Cocody, Abidjan, Côte d'Ivoire ». Vide quand la
  /// réponse ne tient qu'en un segment.
  final String contexte;
}

/// Coupe le `display_name` de Nominatim en nom de lieu et contexte.
///
/// Le site avait exactement le défaut que cette fonction évite : son gabarit
/// d'affichage n'assemblait que rue, ville, région et pays, et jetait le nom du
/// lieu. Une recherche sur « Riviera » y produisait deux propositions écrites
/// « Yamoussoukro » et « Abidjan » — impossible de savoir laquelle est
/// laquelle, ni même de reconnaître ce qu'on avait cherché.
///
/// Nominatim renvoie toujours `display_name`, du plus précis au plus large. Le
/// premier segment est donc le lieu, le reste le situe. C'est vrai pour toutes
/// les réponses, là où un assemblage par champs d'adresse laisse des trous —
/// les repères sans rue (marchés, gares, carrefours) sont justement ce qu'un
/// client saisit pour se faire livrer.
LibelleLieu decouperLibelle(String displayName) {
  final segments = displayName
      .split(',')
      .map((m) => m.trim())
      .where((m) => m.isNotEmpty)
      .toList();

  if (segments.isEmpty) {
    return const LibelleLieu('', '');
  }

  return LibelleLieu(segments.first, segments.skip(1).join(', '));
}

/// Tolérance de comparaison entre deux points, en degrés.
///
/// Environ un mètre. Déplacer la carte, même à peine, dépasse largement cette
/// valeur ; en revanche le recentrage automatique sur un lieu choisi peut
/// laisser un écart infime dû à l'arrondi de la caméra.
const double toleranceMemePoint = 0.00001;

/// Deux coordonnées désignent-elles le même point ?
///
/// Sert à savoir si le client a bougé la carte DEPUIS qu'il a choisi un lieu
/// dans la liste. S'il ne l'a pas bougée, l'adresse qu'il a retenue reste la
/// bonne : il ne faut pas la remplacer.
bool memePoint(
  double latitudeA,
  double longitudeA,
  double latitudeB,
  double longitudeB,
) =>
    (latitudeA - latitudeB).abs() <= toleranceMemePoint &&
    (longitudeA - longitudeB).abs() <= toleranceMemePoint;
