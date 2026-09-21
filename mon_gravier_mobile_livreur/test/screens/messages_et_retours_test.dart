import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// UN MESSAGE NE S'AFFICHE PAS SUR L'ÉCRAN DE QUELQU'UN D'AUTRE.
///
/// Constaté le 03/09/2026, capture à l'appui : « Impossible de contacter le
/// serveur » s'affichait sur « Mon compte », et « Une erreur s'est produite »
/// sur « Gestion des véhicules » — deux écrans qui n'avaient rien demandé.
///
/// La cause : ces écrans lancent une requête dès leur affichage. Si l'on
/// change de page pendant qu'elle tourne, sa réponse revient alors qu'on est
/// DÉJÀ AILLEURS, et le message part sur l'écran courant. `mounted` dit si
/// l'écran qui a lancé la requête est encore là.
///
/// L'essai lit la SOURCE : rejouer une navigation rapide dans un essai de
/// widget demanderait de monter toute l'application et un serveur factice,
/// pour vérifier une règle qui se lit en une ligne.
void main() {
  /// Les écrans qui chargent dès leur affichage — ceux qu'on quitte en
  /// laissant leur requête en vol.
  const ecransQuiChargentALArrivee = [
    'lib/screens/home/home_screen.dart',
    'lib/screens/livraison/livraison_screen.dart',
    'lib/screens/vehicule/vehicule_screen.dart',
    'lib/screens/commande/commande_screen.dart',
    'lib/screens/demande_retrait/liste_demande_retrait_screen.dart',
  ];

  test("aucun message ne part d'un écran déjà quitté", () {
    final fautifs = <String>[];

    for (final chemin in ecransQuiChargentALArrivee) {
      final lignes = File(chemin).readAsLinesSync();

      for (var i = 0; i < lignes.length; i++) {
        final l = lignes[i];
        if (!RegExp(r'^\s*afficher(Erreur|Info)\(').hasMatch(l)) {
          continue;
        }
        if (!l.contains('if (mounted)')) {
          fautifs.add('$chemin:${i + 1}');
        }
      }
    }

    expect(fautifs, isEmpty,
        reason: 'Ces messages partiront sur l écran où l utilisateur se '
            'trouve au moment de la réponse, et non sur celui qui a posé la '
            'question : ${fautifs.join(', ')}');
  });

  test("aucun écran n'est redessiné après avoir été quitté", () {
    final fautifs = <String>[];

    for (final chemin in ecransQuiChargentALArrivee) {
      final lignes = File(chemin).readAsLinesSync();

      for (var i = 0; i < lignes.length; i++) {
        if (!RegExp(r'^\s*setState\(\(\) \{\s*$').hasMatch(lignes[i])) {
          continue;
        }
        final garde = lignes[i].contains('if (mounted)') ||
            (i > 0 && lignes[i - 1].contains('mounted'));

        if (!garde) {
          fautifs.add('$chemin:${i + 1}');
        }
      }
    }

    expect(fautifs, isEmpty,
        reason: '`setState` sur un écran détruit lève une exception que le '
            'cadre avale : il reste un défaut opaque, sans explication. '
            '${fautifs.join(', ')}');
  });

  /// APRÈS UNE LIVRAISON CLOSE, ON REVIENT À LA LISTE. TOUJOURS.
  ///
  /// `Get.until((route) => route.isFirst)` dépile jusqu'à l'écran racine puis
  /// laisse voir L'ONGLET COURANT, quel qu'il soit : si l'onglet du bas avait
  /// changé pendant l'attente, le livreur se retrouvait sur l'accueil, sans
  /// sa liste et sans comprendre.
  test("la fin d'une livraison ramène à la liste, pas à l'accueil", () {
    final source =
        File('lib/screens/livraison/components/livraison_effectuee_screen.dart')
            .readAsStringSync();

    expect(source.contains('ouvrirLivraisons(ONGLET_LIVRAISON_EFFECTUEE)'), isTrue,
        reason: "L'onglet d'arrivée doit être DÉSIGNÉ, et non hérité de ce qui "
            'se trouve sous la pile de navigation.');

    // Le dépilement reste nécessaire : sans lui, on resterait sur le détail
    // d'une livraison qu'on vient de clore.
    expect(source.contains('Get.until((route) => route.isFirst)'), isTrue,
        reason: 'Sans dépilement, on reste sur le détail de la livraison close.');
  });
}
