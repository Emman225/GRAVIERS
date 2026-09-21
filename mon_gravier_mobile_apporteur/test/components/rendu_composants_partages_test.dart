import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_apporteur/components/carte_operation.dart';
import 'package:mon_gravier_com_apporteur/components/etat_vide.dart';
import 'package:mon_gravier_com_apporteur/theme.dart';
import 'package:searchable_listview/searchable_listview.dart';

/// LES COMPOSANTS PARTAGÉS DOIVENT TENIR, MÊME SUR LE PLUS PETIT TÉLÉPHONE.
///
/// Ces composants viennent de l'application client, où ils ont déjà révélé deux
/// défauts que ni l'analyse ni la compilation ne pouvaient voir :
///
///  · une pastille d'état non contrainte débordait la carte de 106 px sur un
///    écran de 320 px de large ;
///  · un rappel `onTap` VIDE sur la carte étouffait le geste porté autour
///    d'elle, rendant trois écrans muets au toucher.
///
/// Les mêmes essais tournent donc ici : porter un composant sans porter ce qui
/// le vérifie, c'est porter le composant ET la possibilité de le recasser.
void main() {
  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: Scaffold(body: enfant),
        ),
      );

  /// Trois tailles couvrant le parc réel.
  const tailles = <String, Size>{
    'petit (320 x 568)': Size(320, 568),
    'standard (360 x 640)': Size(360, 640),
    'grand (412 x 915)': Size(412, 915),
  };

  Future<void> surChaqueEcran(
    WidgetTester tester,
    Widget Function() construire,
  ) async {
    for (final entree in tailles.entries) {
      tester.view.physicalSize = entree.value;
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(ecran(construire()));
      await tester.pump();

      expect(tester.takeException(), isNull,
          reason: 'Débordement sur écran ${entree.key}');
    }
  }

  group("Carte d'opération", () {
    testWidgets('tient avec toutes ses lignes renseignées', (tester) async {
      await surChaqueEcran(
        tester,
        () => ListView(
          children: [
            for (int i = 0; i < 3; i++)
              const Padding(
                padding: EdgeInsets.all(16),
                child: CarteOperation(
                  numero: 'Livraison n° 849677',
                  montant: '28 813 F',
                  mention: 'Paiement : Paiement en agence',
                  date: '25 août 2026 à 14h30',
                  statut: 'EN TRAITEMENT',
                ),
              ),
          ],
        ),
      );
    });

    testWidgets('supporte des valeurs anormalement longues', (tester) async {
      await surChaqueEcran(
        tester,
        () => const Padding(
          padding: EdgeInsets.all(16),
          child: CarteOperation(
            numero: 'Livraison n° 8496770000112233445566778899',
            montant: '128 813 456 F',
            montantBarre: '132 536 789 F',
            mention:
                'Paiement : virement bancaire avec justificatif transmis au guichet',
            date: 'mercredi 25 août 2026 à 14 heures 30 minutes',
            statut: 'EN ATTENTE DE TRAITEMENT',
          ),
        ),
      );
    });

    testWidgets('toucher la carte appelle son action', (tester) async {
      int appels = 0;

      await tester.pumpWidget(ecran(
        CarteOperation(
          numero: 'Livraison n° 849677',
          montant: '28 813 F',
          onTap: () => appels++,
        ),
      ));

      await tester.tap(find.byType(CarteOperation));
      await tester.pumpAndSettle();

      expect(appels, 1, reason: "L'action de la carte doit être déclenchée.");
    });

    testWidgets('un rappel VIDE étouffe le geste extérieur', (tester) async {
      int autour = 0;

      await tester.pumpWidget(ecran(
        GestureDetector(
          onTap: () => autour++,
          child: CarteOperation(
            numero: 'Livraison n° 849677',
            montant: '28 813 F',
            // LA FAUTE, reproduite : une réservation d'apparence inoffensive.
            onTap: () {},
          ),
        ),
      ));

      await tester.tap(find.byType(CarteOperation));
      await tester.pumpAndSettle();

      expect(autour, 0,
          reason: "Un InkWell muni d'une action, même vide, absorbe le geste. "
              "L'action doit être passée à `CarteOperation.onTap`.");
    });
  });

  group('État vide', () {
    testWidgets('tient avec un message long et un bouton', (tester) async {
      await surChaqueEcran(
        tester,
        () => EtatVide(
          icone: Icons.inbox_outlined,
          titre: 'Aucune donnée pour le moment',
          message: "Cette liste se remplira dès que des éléments vous seront "
              "attribués. Revenez un peu plus tard.",
          libelleAction: 'Réessayer',
          action: () {},
        ),
      );
    });

    /// Les huit listes des deux applications passent un EtatVide au paramètre
    /// `emptyWidget` de SearchableList, qui le rend dans une Column. Ce
    /// composant porte son propre défilement : c'est la seule composition où il
    /// n'est pas posé directement dans le corps d'un écran.
    testWidgets('tient comme emptyWidget d\'une liste cherchable',
        (tester) async {
      await surChaqueEcran(
        tester,
        () => SearchableList<String>(
          shrinkWrap: true,
          searchFieldEnabled: true,
          initialList: const <String>[],
          builder: (liste, index, element) => Text(element),
          filter: (terme) => const <String>[],
          emptyWidget: const EtatVide(
            compact: true,
            icone: Icons.local_shipping_outlined,
            titre: 'Aucune livraison ici',
            message: "Les livraisons de cet état apparaîtront dans cette liste.",
          ),
        ),
      );
    });
  });
}
