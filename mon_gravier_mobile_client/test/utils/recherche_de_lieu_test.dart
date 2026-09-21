import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/screens/afficher_carte/recherche_de_lieu.dart';

/// LA RECHERCHE DE LIEU DOIT INTERROGER LE SERVICE COMME LE FAIT LE SITE.
///
/// L'écran de carte ne proposait QUE le glissement : aucun champ de saisie.
/// Pour livrer à Yopougon depuis Cocody, il fallait faire glisser la carte sur
/// toute la distance — alors que le site offrait une recherche depuis le début.
///
/// La recherche ajoutée reprend les règles du site
/// (`graviers/public/frontend/assets/leaflet/recherche-lieu.js`). Les deux
/// canaux DOIVENT interroger Nominatim avec les mêmes paramètres : une
/// recherche qui ne donne pas les mêmes lieux d'un canal à l'autre est une
/// source de confusion pour le client comme pour le gestionnaire.
///
/// Le filtre pays est le point décisif, et il est mesuré : sans lui,
/// « Riviera » renvoie le Texas, la Suisse et la Provence ; « Gare routière »
/// renvoie Auxerre et Ghardaïa.
///
/// Cet essai vérifie l'URL CONSTRUITE, sans appeler le réseau : un essai qui
/// dépendrait de Nominatim échouerait au premier incident de connexion, et ne
/// dirait rien sur notre code.
///
/// Il RECOPIAIT auparavant la construction de l'URL pour la contrôler. Un essai
/// qui recopie ce qu'il vérifie ne vérifie rien : l'écran pouvait dériver sans
/// que rien ne le signale. Il appelle maintenant la fonction de production.
void main() {
  Uri construire(String terme) => uriRechercheLieu(terme);

  test('la recherche est limitée à la Côte d\'Ivoire', () {
    final uri = construire('Riviera');

    expect(uri.queryParameters['countrycodes'], 'ci',
        reason: "Sans ce filtre, « Riviera » renvoie le Texas et la Suisse "
            "avant les quartiers d'Abidjan.");
  });

  test('les réponses sont demandées en français, avec le détail', () {
    final uri = construire('Marché');

    expect(uri.queryParameters['accept-language'], 'fr');
    expect(uri.queryParameters['addressdetails'], '1');
  });

  test('la liste est bornée, comme sur le site', () {
    expect(construire('Gare').queryParameters['limit'], '8');
  });

  test('le terme cherché est correctement encodé', () {
    // Un quartier avec accent ou espace ne doit pas casser l'adresse appelée.
    final uri = construire('Gare routière');

    expect(uri.queryParameters['q'], 'Gare routière');
    expect(uri.toString(), contains('nominatim.openstreetmap.org/search'));
  });

  test('les paramètres sont ceux du site, aucun de plus, aucun de moins', () {
    // Le fichier `recherche-lieu.js` transmet exactement ces clés à Nominatim.
    // Si l'un des deux canaux dérive, cet essai le signale.
    expect(
      construire('Cocody').queryParameters.keys.toSet(),
      {'format', 'q', 'countrycodes', 'accept-language', 'addressdetails', 'limit'},
    );
  });

  group('le lieu choisi prime sur le géocodage inverse', () {
    // La validation relançait TOUJOURS un géocodage inverse, qui écrasait
    // l'adresse retenue dans la liste. Quand le service ne répondait pas, le
    // repli remplaçait « Riviéra 3, Cocody, Abidjan » par les coordonnées
    // brutes — et c'est cela que le livreur recevait.
    //
    // La règle : tant que la carte n'a pas bougé depuis le choix, on ne
    // réinterroge rien.
    const latChoisie = 5.3577727;
    const lonChoisie = -3.8885591;

    test('la carte immobile conserve le lieu choisi', () {
      expect(memePoint(latChoisie, lonChoisie, latChoisie, lonChoisie), isTrue);
    });

    test("l'arrondi du recentrage ne compte pas pour un déplacement", () {
      // Le recentrage automatique peut laisser un écart infime.
      expect(
        memePoint(latChoisie, lonChoisie,
            latChoisie + 0.000002, lonChoisie - 0.000003),
        isTrue,
      );
    });

    test('un vrai déplacement de la carte redonne la main au service', () {
      // Une centaine de mètres : le client a bougé, son adresse a changé.
      expect(memePoint(latChoisie, lonChoisie, latChoisie + 0.001, lonChoisie),
          isFalse);
      expect(memePoint(latChoisie, lonChoisie, latChoisie, lonChoisie + 0.001),
          isFalse);
    });

    test('deux quartiers distincts ne sont jamais confondus', () {
      // Bingerville et Yopougon, mesurés lors des essais sur le site.
      expect(memePoint(5.3577727, -3.8885591, 5.335194, -4.075756), isFalse);
    });
  });

  group('libellé des suggestions', () {
    // Le site portait exactement ce défaut : son gabarit d'affichage jetait le
    // nom du lieu et n'assemblait que ville, région et pays. « Riviera » y
    // proposait deux lignes écrites « Yamoussoukro » et « Abidjan » —
    // indiscernables. Le mobile doit montrer CE QUI A ÉTÉ CHERCHÉ.
    test('le nom du lieu vient en premier, jamais sa ville', () {
      final l = decouperLibelle('Riviéra 3, Cocody, Abidjan, Côte d’Ivoire');

      expect(l.nom, 'Riviéra 3');
      expect(l.contexte, 'Cocody, Abidjan, Côte d’Ivoire');
      expect(l.nom, isNot('Abidjan'),
          reason: "C'est le travers corrige sur le site : deux propositions "
              'réduites à « Abidjan » ne se distinguent plus.');
    });

    test('deux quartiers homonymes restent distinguables', () {
      final a = decouperLibelle('Riviera, Yamoussoukro, Côte d’Ivoire');
      final b = decouperLibelle('Riviéra 3, Cocody, Abidjan, Côte d’Ivoire');

      expect(a.nom == b.nom && a.contexte == b.contexte, isFalse);
    });

    test('un repère sans rue garde son nom', () {
      // Marchés, gares, carrefours : justement ce qu'un client saisit pour se
      // faire livrer, et ce que l'assemblage par champs d'adresse perdait.
      final l = decouperLibelle('Marché de Cocody, Cocody, Abidjan');

      expect(l.nom, 'Marché de Cocody');
    });

    test("une reponse en un seul segment n'a pas de contexte", () {
      final l = decouperLibelle('Abidjan');

      expect(l.nom, 'Abidjan');
      expect(l.contexte, isEmpty);
    });

    test('les espaces superflus et segments vides sont écartés', () {
      final l = decouperLibelle('  Cocody ,, Abidjan ,  ');

      expect(l.nom, 'Cocody');
      expect(l.contexte, 'Abidjan');
    });

    test("une reponse vide ne fait pas tomber l'ecran", () {
      final l = decouperLibelle('');

      expect(l.nom, isEmpty);
      expect(l.contexte, isEmpty);
    });
  });
}
