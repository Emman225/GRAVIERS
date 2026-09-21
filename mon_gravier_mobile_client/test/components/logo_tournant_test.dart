import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/components/logo_tournant.dart';

/// L'INDICATEUR DE CHARGEMENT NE DOIT JAMAIS ÉCHOUER.
///
/// Il a remplacé l'anneau générique d'EasyLoading, et il s'affiche donc à
/// CHAQUE attente : catalogue, commande, facture, connexion. Une exception ici
/// ne casserait pas un écran, elle les casserait tous — et au pire moment,
/// celui où l'application est déjà en train d'attendre le serveur.
///
/// Ces essais vérifient qu'il se construit, qu'il tourne réellement, et qu'il
/// survit à l'absence du fichier de logo.
void main() {
  testWidgets('se construit et tourne sans lever d\'exception', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Center(child: LogoTournant())),
    ));

    expect(find.byType(LogoTournant), findsOneWidget);
    expect(tester.takeException(), isNull);

    // L'animation tourne en boucle : on avance dans le temps pour s'assurer
    // qu'aucune image n'est demandée après coup sur un état détruit.
    await tester.pump(const Duration(milliseconds: 700));
    await tester.pump(const Duration(milliseconds: 700));
    expect(tester.takeException(), isNull);
  });

  testWidgets('la rotation est bien animée', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Center(child: LogoTournant())),
    ));

    // On vise la rotation DU LOGO, et pas n'importe laquelle : lorsque le
    // fichier de logo manque, le repli affiche un CircularProgressIndicator qui
    // porte lui aussi une RotationTransition. Sans ce ciblage, le sélecteur en
    // trouvait deux et l'essai échouait sur « Too many elements ».
    double angle() => tester
        .widget<RotationTransition>(find
            .descendant(
              of: find.byType(LogoTournant),
              matching: find.byType(RotationTransition),
            )
            .first)
        .turns
        .value;

    final depart = angle();
    await tester.pump(const Duration(milliseconds: 500));
    expect(angle(), isNot(depart),
        reason: "Sans rotation, l'indicateur ne dit plus que l'application "
            "travaille : c'est une image fixe.");
  });

  testWidgets('se retire proprement', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Center(child: LogoTournant())),
    ));
    await tester.pump(const Duration(milliseconds: 300));

    // EasyLoading insère puis RETIRE cet indicateur à chaque attente : son
    // contrôleur d'animation doit être libéré sans bruit, sinon chaque
    // chargement fuirait un peu de mémoire.
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: SizedBox.shrink()),
    ));

    expect(find.byType(LogoTournant), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
