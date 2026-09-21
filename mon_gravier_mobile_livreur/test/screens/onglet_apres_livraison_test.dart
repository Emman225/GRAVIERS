import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/livraison_screen.dart';
import 'package:mon_gravier_com_livreur/theme.dart';

/// APRÈS UNE LIVRAISON CLOSE, L'ONGLET « EFFECTUÉE » EST OUVERT.
///
/// Signalé le 03/09/2026 : le livreur valide le code, revient bien sur la
/// liste — mais sur le premier onglet, « En Attente », où la livraison qu'il
/// vient de clore ne figure évidemment plus. Il doit taper un troisième
/// onglet pour la retrouver.
///
/// L'essai rejoue le geste exact : l'écran est affiché sur son premier
/// onglet, puis `ouvrirLivraisons` est appelé comme le fait l'écran de fin de
/// livraison.
void main() {
  setUpAll(() async {
    await initializeDateFormatting('fr_FR', null);
  });

  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: enfant,
        ),
      );

  tearDown(() {
    allerAOnglet = null;
    allerAOngletLivraison = null;
  });

  testWidgets("« Effectuée » s'ouvre après la validation du code",
      (tester) async {
    int? ongletDuBas;
    allerAOnglet = (i) => ongletDuBas = i;

    await tester.pumpWidget(ecran(const LivraisonScreen()));
    await tester.pump();

    final onglets =
        tester.widget<TabBarView>(find.byType(TabBarView)).controller!;

    expect(onglets.index, 0, reason: "On arrive sur le premier onglet.");

    // LE GESTE EXACT de l'écran de fin de livraison.
    ouvrirLivraisons(ONGLET_LIVRAISON_EFFECTUEE);
    await tester.pumpAndSettle();

    expect(ongletDuBas, 1,
        reason: "On doit revenir sur l'onglet « Livraison » de la barre du bas.");
    expect(onglets.index, ONGLET_LIVRAISON_EFFECTUEE,
        reason: "Le livreur retombe sur « En Attente », où la livraison qu'il "
            "vient de clore ne figure plus : il doit chercher un troisième "
            "onglet pour la retrouver.");

    // ET IL DOIT Y RESTER. Le chargement de la liste se termine après coup et
    // reconstruit les pages : si l'onglet se remettait à zéro à ce moment-là,
    // le défaut serait le même, en plus difficile à voir.
    await tester.pump(const Duration(seconds: 1));
    expect(onglets.index, ONGLET_LIVRAISON_EFFECTUEE,
        reason: "L'onglet est retombé sur le premier après le rechargement.");
  });

  testWidgets("« Effectuée » s'ouvre AUSSI quand l'écran n'existait pas encore",
      (tester) async {
    // LE CAS QUE J'AVAIS MANQUÉ.
    //
    // Depuis que les onglets se construisent à la première visite, l'écran
    // des livraisons peut ne pas exister au moment de la demande : le point
    // d'entrée est alors nul, et la demande tombait dans le vide. Le livreur
    // retombait sur « En Attente ».
    allerAOngletLivraison = null;
    ongletLivraisonDemande = null;

    int? ongletDuBas;
    allerAOnglet = (i) => ongletDuBas = i;

    // La demande part alors que l'écran n'est pas là.
    ouvrirLivraisons(ONGLET_LIVRAISON_EFFECTUEE);

    expect(ongletDuBas, 1);
    expect(ongletLivraisonDemande, ONGLET_LIVRAISON_EFFECTUEE,
        reason: "La demande doit être retenue, pas perdue.");

    // L'écran est construit ensuite, comme le fait l'IndexedStack paresseux.
    await tester.pumpWidget(ecran(const LivraisonScreen()));
    await tester.pumpAndSettle();

    final onglets =
        tester.widget<TabBarView>(find.byType(TabBarView)).controller!;

    expect(onglets.index, ONGLET_LIVRAISON_EFFECTUEE,
        reason: "L'écran doit appliquer la demande en arrivant.");
    expect(ongletLivraisonDemande, isNull,
        reason: "Une demande consommée ne doit pas rouvrir cet onglet la "
            "prochaine fois qu'on va aux livraisons.");
  });
}
