import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/components/carte_operation.dart';
import 'package:mon_gravier_com/theme.dart';

/// TOUCHER UNE CARTE DOIT DÉCLENCHER SON ACTION.
///
/// La refonte des listes a remplacé le contenu de chaque ligne par une
/// `CarteOperation`, qui porte un `InkWell`. L'action réelle, elle, était
/// restée sur le `GestureDetector` qui ENVELOPPAIT la ligne — et la carte a
/// reçu, en attendant, un `onTap: () {}` vide.
///
/// C'est ce rappel VIDE qui a tout cassé : un InkWell muni d'une action, fût-
/// elle sans effet, absorbe le geste et le détecteur extérieur n'est jamais
/// appelé. (Avec `onTap: null`, l'InkWell laisse au contraire passer — ces
/// essais le vérifient aussi.)
///
/// Résultat, sur trois écrans, toucher une ligne ne faisait plus rien du tout :
/// « Paiements effectués » (impression du reçu), « Mes demandes de livraison »
/// (ouverture du détail) et « Mes devis ». Ni l'analyse ni la compilation ne
/// pouvaient le voir : l'application se contentait de ne plus répondre.
///
/// Règle fixée ici : l'action se porte SUR la carte — jamais autour, et jamais
/// en réservant un rappel vide.
void main() {
  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: Scaffold(body: enfant),
        ),
      );

  testWidgets('toucher la carte appelle son action', (tester) async {
    int appels = 0;

    await tester.pumpWidget(ecran(
      CarteOperation(
        numero: 'Règlement RC-2026-002',
        montant: '55 920 F',
        date: '25 août 2026',
        onTap: () => appels++,
      ),
    ));

    await tester.tap(find.byType(CarteOperation));
    await tester.pumpAndSettle();

    expect(appels, 1, reason: "L'action de la carte doit être déclenchée.");
  });

  testWidgets('un rappel VIDE sur la carte étouffe le geste extérieur',
      (tester) async {
    int autour = 0;

    await tester.pumpWidget(ecran(
      GestureDetector(
        onTap: () => autour++,
        child: CarteOperation(
          numero: 'Règlement RC-2026-002',
          montant: '55 920 F',
          date: '25 août 2026',
          // LA FAUTE, reproduite : une réservation d'apparence inoffensive.
          onTap: () {},
        ),
      ),
    ));

    await tester.tap(find.byType(CarteOperation));
    await tester.pumpAndSettle();

    expect(autour, 0,
        reason: "Un InkWell muni d'une action, même vide, absorbe le geste : "
            "le détecteur extérieur n'est jamais appelé. C'est exactement ce "
            "qui a rendu trois écrans muets.");
  });

  testWidgets('sans action, la carte laisse passer le geste extérieur',
      (tester) async {
    int autour = 0;

    await tester.pumpWidget(ecran(
      GestureDetector(
        onTap: () => autour++,
        child: const CarteOperation(
          numero: 'Règlement RC-2026-002',
          montant: '55 920 F',
          date: '25 août 2026',
        ),
      ),
    ));

    await tester.tap(find.byType(CarteOperation));
    await tester.pumpAndSettle();

    expect(autour, 1,
        reason: "Avec `onTap: null`, l'InkWell n'intercepte rien.");
  });

  testWidgets('sans action, la carte ne réagit pas', (tester) async {
    await tester.pumpWidget(ecran(
      const CarteOperation(
        numero: 'Devis n° 4412',
        montant: '128 000 F',
        statut: 'Passé en commande',
      ),
    ));

    await tester.tap(find.byType(CarteOperation));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull,
        reason: 'Une carte sans action doit rester inerte, pas planter.');
  });
}
