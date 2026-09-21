import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/components/filtre_produits.dart';
import 'package:mon_gravier_com/theme.dart';

/// LE PANNEAU DE FILTRES DU CATALOGUE.
///
/// Trois défauts sont tenus ici :
///   · les boutons étaient en anglais — « Reset » et « Apply » — et écrits en
///     dur dans le paquet employé, sans réglage pour les traduire ;
///   · le panneau rouvrait vierge : le client ne voyait pas quel filtre était
///     actif, et ne pouvait donc pas le retirer ;
///   · fermer sans valider ne devait rien changer, ce qui n'allait pas de soi
///     avec une sélection partagée.
void main() {
  /// Monte le panneau et rend la dernière sélection validée.
  Future<Map<String, List<String>>?> monter(
    WidgetTester tester, {
    Map<String, List<String>> selection = const {},
  }) async {
    Map<String, List<String>>? valide;

    await tester.pumpWidget(Builder(builder: (context) {
      return MaterialApp(
        theme: AppTheme.lightTheme(context),
        home: Scaffold(
        body: FiltreProduits(
          selection: selection,
          groupes: const [
            GroupeFiltre(cle: 'Categorie', titre: 'Catégorie', options: [
              OptionFiltre(cle: '1', libelle: 'Gravier'),
              OptionFiltre(cle: '2', libelle: 'Sable'),
            ]),
            GroupeFiltre(cle: 'Produit', titre: 'Produit', options: [
              OptionFiltre(cle: '10', libelle: 'Sable de mer lavé'),
            ]),
          ],
          onValider: (choix) => valide = choix,
        ),
      ),
      );
    }));
    await tester.pumpAndSettle();

    return valide;
  }

  testWidgets('les boutons sont en français', (tester) async {
    await monter(tester);

    expect(find.text('Réinitialiser'), findsOneWidget);
    expect(find.text('Appliquer'), findsOneWidget);

    expect(find.text('Reset'), findsNothing);
    expect(find.text('Apply'), findsNothing);
  });

  testWidgets('le panneau rouvre sur ce qui est déjà coché', (tester) async {
    await monter(tester, selection: {
      'Categorie': ['1']
    });

    // Le critère actif s'annonce sur son titre, sans avoir à déplier.
    expect(find.text('Catégorie (1)'), findsOneWidget);

    // Et la case correspondante est bien cochée.
    final caseGravier = tester.widget<CheckboxListTile>(
      find.ancestor(
        of: find.text('Gravier'),
        matching: find.byType(CheckboxListTile),
      ),
    );
    expect(caseGravier.value, isTrue);
  });

  testWidgets('cocher puis appliquer rend la sélection', (tester) async {
    Map<String, List<String>>? valide;

    await tester.pumpWidget(Builder(builder: (context) {
      return MaterialApp(
        theme: AppTheme.lightTheme(context),
        home: Scaffold(
        body: FiltreProduits(
          selection: const {},
          groupes: const [
            GroupeFiltre(cle: 'Categorie', titre: 'Catégorie', options: [
              OptionFiltre(cle: '1', libelle: 'Gravier'),
              OptionFiltre(cle: '2', libelle: 'Sable'),
            ]),
          ],
          onValider: (choix) => valide = choix,
        ),
      ),
      );
    }));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Catégorie'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Sable'));
    await tester.pumpAndSettle();

    // Le bouton compte ce qui est coché : le client sait ce qu'il applique.
    expect(find.text('Appliquer (1)'), findsOneWidget);

    await tester.tap(find.text('Appliquer (1)'));
    await tester.pumpAndSettle();

    expect(valide, isNotNull);
    expect(valide!['Categorie'], equals(['2']));
  });

  testWidgets('réinitialiser décoche tout', (tester) async {
    await monter(tester, selection: {
      'Categorie': ['1', '2']
    });

    expect(find.text('Catégorie (2)'), findsOneWidget);

    await tester.tap(find.text('Réinitialiser'));
    await tester.pumpAndSettle();

    expect(find.text('Catégorie'), findsOneWidget);
    expect(find.text('Catégorie (2)'), findsNothing);
  });

  testWidgets('fermer sans valider ne touche pas à la sélection', (tester) async {
    // La liste derrière le panneau ne doit pas bouger tant que rien n'est
    // validé : le panneau travaille sur une COPIE.
    final selection = {
      'Categorie': ['1']
    };

    await monter(tester, selection: selection);

    await tester.tap(find.text('Gravier'));
    await tester.pumpAndSettle();

    expect(selection['Categorie'], equals(['1']),
        reason: "Décocher sans valider ne doit pas modifier la sélection active.");
  });

  testWidgets("le bouton Appliquer tient dans l'écran", (tester) async {
    // LE DÉFAUT SIGNALÉ : le bouton avait disparu.
    //
    // Le thème impose à tout ElevatedButton une largeur minimale infinie — il
    // est prévu pour des boutons pleine largeur, seuls sur leur ligne. Dans une
    // Row, la mise en page échouait et le bouton ne s'affichait pas.
    tester.view.physicalSize = const Size(1080, 2340);
    tester.view.devicePixelRatio = 3.0;
    addTearDown(tester.view.reset);

    await monter(tester);

    final largeurEcran =
        tester.view.physicalSize.width / tester.view.devicePixelRatio;
    final bouton = tester.getRect(find.text('Appliquer'));

    expect(bouton.right, lessThanOrEqualTo(largeurEcran),
        reason: 'Le bouton dépasse le bord droit : il devient invisible.');
    expect(bouton.left, greaterThanOrEqualTo(0.0));

    // Et il répond au doigt, ce qu'un bouton hors écran ne fait pas.
    await tester.tap(find.text('Appliquer'));
    await tester.pumpAndSettle();
  });

  testWidgets('la recherche restreint les options proposées', (tester) async {
    await monter(tester, selection: {
      'Categorie': ['1']
    });

    await tester.enterText(find.byType(TextField), 'sab');
    await tester.pumpAndSettle();

    expect(find.text('Sable'), findsOneWidget);
    expect(find.text('Gravier'), findsNothing);
  });
}
