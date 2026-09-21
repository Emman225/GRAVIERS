import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// ON NE RECHARGE QU'À LA PREMIÈRE VISITE.
///
/// Signalé le 03/09/2026 : « le loader tourne à chaque fois qu'on repasse sur
/// un écran déjà chargé ». La cause : `body: pages[currentSelectedIndex]` ne
/// place qu'UN écran dans l'arbre. Les autres en sortent, leur état est
/// détruit, et y revenir les reconstruit de zéro — voile de chargement et
/// appel à l'API compris, pour des données chargées une minute plus tôt.
///
/// `IndexedStack` les garde tous : `initState` ne s'exécute qu'une fois par
/// écran.
///
/// CE QUE CE CHANGEMENT EMPORTE AVEC LUI : changer d'onglet était, jusque-là,
/// la seule façon d'actualiser — involontaire, mais réelle. Chaque écran doit
/// donc offrir le glisser, sans quoi on aurait remplacé un défaut par une
/// régression.
void main() {
  String source(String chemin) => File(chemin).readAsStringSync();

  /// Le code VIVANT du fichier : les lignes commentees ne comptent pas.
  ///
  /// Sans cela, mettre un appel en commentaire laissait la garde passer — le
  /// texte etait toujours la, et l'essai se declarait satisfait.
  String codeVivant(String chemin) => File(chemin)
      .readAsLinesSync()
      .where((l) => !l.trimLeft().startsWith('//'))
      .join(String.fromCharCode(10));


  test("les onglets déjà visités restent vivants", () {
    final s = codeVivant('lib/screens/init_screen.dart');

    expect(s.contains('IndexedStack('), isTrue,
        reason: "Sans lui, revenir sur un onglet le reconstruit de zéro : "
            'voile de chargement et appel à l API compris.');
    expect(s.contains('child: pages[currentSelectedIndex]'), isFalse,
        reason: "L'ancien affichage d'un seul écran est revenu.");
  });

  test("un onglet jamais ouvert n'appelle pas l'API", () {
    final s = codeVivant('lib/screens/init_screen.dart');

    expect(s.contains('_dejaVisites'), isTrue,
        reason: 'Sans cette paresse, les quatre écrans appelleraient l API en '
            'même temps au démarrage : on aurait remplacé un défaut par un '
            'autre.');
  });

  /// Les écrans d'onglet qui chargent des données doivent tous se laisser
  /// tirer vers le bas.
  test("chaque écran chargé se rafraîchit au glisser", () {
    const ecrans = {
      'lib/screens/home/home_screen.dart': 'accueil',
      'lib/screens/commission/commission_screen.dart': 'commissions',
      'lib/screens/filleule/filleule_screen.dart': 'filleuls',
    };

    final sans = <String>[];

    ecrans.forEach((chemin, nom) {
      final s = source(chemin);
      final aLeGeste = s.contains('RefreshIndicator(') || s.contains('onRefresh:');
      if (!aLeGeste) sans.add(nom);
    });

    expect(sans, isEmpty,
        reason: "Ces écrans n'ont plus AUCUN moyen d'être actualisés : "
            "${sans.join(', ')}");
  });

  test("le glisser ne repose pas le voile de chargement par-dessus", () {
    const chargeurs = [
      'lib/screens/commission/commission_screen.dart',
      'lib/screens/filleule/filleule_screen.dart',
    ];

    for (final chemin in chargeurs) {
      expect(source(chemin).contains('sansLoader'), isTrue,
          reason: "$chemin : le voile masquerait justement ce qu'on vient de "
              'tirer pour voir.');
    }
  });

  /// Une liste plus courte que l'écran ne défile pas : le glisser n'atteint
  /// alors jamais l'indicateur, et le geste semble mort.
  test("les listes restent tirables même quand elles sont courtes", () {
    const listes = [
      'lib/screens/commission/commission_screen.dart',
      'lib/screens/filleule/filleule_screen.dart',
    ];

    for (final chemin in listes) {
      expect(source(chemin).contains('AlwaysScrollableScrollPhysics'), isTrue,
          reason: '$chemin : sur une liste courte, le geste ne partirait pas.');
    }
  });

  /// UN CHIFFRE FAUX EST PIRE QU'UN CHIFFRE ABSENT : ON LE CROIT.
  test("l'accueil est prevenu quand une action change ses chiffres", () {
    expect(
        codeVivant('lib/screens/demande_retrait/edit_demande_retrait_screen.dart')
            .contains('rafraichirAccueil?.call()'),
        isTrue,
        reason: "Apres une demande de retrait, le solde de l'accueil "
            "afficherait encore celui d'avant.");
  });

  test("l'accueil se laisse recharger, et se retire en partant", () {
    final s = codeVivant('lib/screens/home/home_screen.dart');

    expect(s.contains('rafraichirAccueil = () async {'), isTrue);
    expect(s.contains('rafraichirAccueil = null;'), isTrue);
    expect(s.contains('sansLoader: true'), isTrue,
        reason: 'Le rechargement doit etre silencieux.');
  });
}
