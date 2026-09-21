import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mon_gravier_com_livreur/components/carte_operation.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/home/home_screen.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_liste_screen.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/livraison_screen.dart';
import 'package:mon_gravier_com_livreur/theme.dart';

/// L'ACCUEIL MÈNE QUELQUE PART, ET LES LISTES S'ACTUALISENT DU DOIGT.
///
/// Trois défauts que ni l'analyse ni la compilation ne voient :
///
///  · les deux tuiles de l'accueil affichaient un nombre — « 3 courses en
///    attente » — sans aucun moyen d'aller les voir ;
///  · les listes ne se rafraîchissaient qu'en tapant une icône de la barre du
///    haut : le glisser, geste que tout le monde essaie d'abord, ne faisait
///    rien ;
///  · une liste VIDE perdait même cette possibilité, `SearchableList`
///    remplaçant l'indicateur par l'état vide — et une liste vide est
///    justement celle qu'on veut recharger.
void main() {
  // `formaterDate` demande les symboles francais. L'application les recoit du
  // `GlobalMaterialLocalizations` de son `MaterialApp` ; l'essai, lui, doit les
  // charger — sans quoi chaque carte se remplace par un rectangle d'erreur.
  setUpAll(() async {
    await initializeDateFormatting('fr_FR', null);
  });

  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: enfant,
        ),
      );

  UneLivraison uneLivraison({
    String etat = LIVRAISON_EN_ATTENTE,
    String be = 'BE-2026-001',
  }) {
    return UneLivraison(
      id: 1,
      numero: 'LIV-001',
      code_enlevement: be,
      nomClient: 'Kouassi',
      contactClient: '0700000000',
      nom_fournisseur: 'Carrière du Sud',
      typeLivraison: 'Camion',
      adresse: 'Cocody Angré',
      coutLivraison: 25000,
      dateLivraison: '2026-09-01 08:00:00',
      updatedAt: '2026-09-02 10:00:00',
      etatLivraison: etat,
      detailCommandeId: 5,
    );
  }

  group("Les tuiles de l'accueil", () {
    setUp(() {
      allerAOnglet = null;
      allerAOngletLivraison = null;
    });

    tearDown(() {
      allerAOnglet = null;
      allerAOngletLivraison = null;
    });

    testWidgets("« EN ATTENTE » ouvre les livraisons en attente",
        (tester) async {
      int? ongletDuBas;
      int? ongletLivraison;
      allerAOnglet = (i) => ongletDuBas = i;
      allerAOngletLivraison = (i) => ongletLivraison = i;

      await tester.pumpWidget(ecran(const HomeScreen()));
      await tester.pump();

      await tester.tap(find.text("EN ATTENTE"));
      await tester.pump();

      expect(ongletDuBas, 1,
          reason: "La tuile doit amener sur l'écran des livraisons.");
      expect(ongletLivraison, ONGLET_LIVRAISON_EN_ATTENTE,
          reason: "Elle doit ouvrir l'onglet « En Attente », pas un autre.");
    });

    testWidgets("« EFFECTUÉES » ouvre les livraisons effectuées",
        (tester) async {
      int? ongletDuBas;
      int? ongletLivraison;
      allerAOnglet = (i) => ongletDuBas = i;
      allerAOngletLivraison = (i) => ongletLivraison = i;

      await tester.pumpWidget(ecran(const HomeScreen()));
      await tester.pump();

      await tester.tap(find.text("EFFECTUÉES"));
      await tester.pump();

      expect(ongletDuBas, 1);
      expect(ongletLivraison, ONGLET_LIVRAISON_EFFECTUEE,
          reason: "Elle doit ouvrir l'onglet « Effectuée », pas le premier.");
    });
  });

  group("La liste des livraisons", () {
    testWidgets("prend la carte partagée, et non l'ancien pavé gris",
        (tester) async {
      await tester.pumpWidget(ecran(Scaffold(
        body: LivraisonListeScreen(livraisons: [uneLivraison()]),
      )));
      await tester.pump();

      expect(find.byType(CarteOperation), findsOneWidget,
          reason: "La liste doit utiliser la carte des paiements effectués.");
    });

    testWidgets("ne perd RIEN de ce que portait l'ancienne carte",
        (tester) async {
      await tester.pumpWidget(ecran(Scaffold(
        body: LivraisonListeScreen(
            livraisons: [uneLivraison(etat: LIVRAISON_EN_TRAITEMENT)]),
      )));
      await tester.pump();

      // Un livreur qui ne lit plus l'adresse ou le contact du client dans sa
      // liste, c'est une régression, pas une refonte.
      for (final attendu in [
        'BE-2026-001',
        'Carrière du Sud',
        'Kouassi',
        '0700000000',
        'Camion',
        'Cocody Angré',
      ]) {
        expect(find.textContaining(attendu), findsWidgets,
            reason: "« $attendu » a disparu de la liste.");
      }
    });

    testWidgets("s'actualise en glissant du haut vers le bas", (tester) async {
      int appels = 0;

      await tester.pumpWidget(ecran(Scaffold(
        body: LivraisonListeScreen(
          livraisons: [uneLivraison()],
          onRafraichir: () async => appels++,
        ),
      )));
      await tester.pump();

      expect(find.byType(RefreshIndicator), findsOneWidget,
          reason: "Le glisser n'est pas branché sur la liste.");

      await tester.fling(
          find.byType(CarteOperation), const Offset(0, 320), 1000);
      await tester.pumpAndSettle();

      expect(appels, 1, reason: "Le glisser n'a rien rechargé.");
    });

    testWidgets("s'actualise AUSSI quand elle est vide", (tester) async {
      int appels = 0;

      await tester.pumpWidget(ecran(Scaffold(
        body: LivraisonListeScreen(
          livraisons: const [],
          onRafraichir: () async => appels++,
        ),
      )));
      await tester.pump();

      expect(find.byType(RefreshIndicator), findsOneWidget,
          reason: "Une liste vide est justement celle qu'on veut recharger.");

      await tester.fling(
          find.byType(ListView), const Offset(0, 320), 1000);
      await tester.pumpAndSettle();

      expect(appels, 1, reason: "Le glisser n'a rien rechargé sur liste vide.");
    });
  });

  group("L'écran des véhicules", () {
    // L'écran déclenche un appel réseau dès son affichage : on vérifie ici ce
    // qui se lit dans sa source, et le comportement commun est déjà couvert par
    // les essais de la liste des livraisons ci-dessus.
    final source = File('lib/screens/vehicule/vehicule_screen.dart')
        .readAsStringSync();

    test("prend la carte partagée", () {
      expect(source.contains('CarteOperation('), isTrue,
          reason: "La liste des véhicules doit utiliser la carte partagée.");
      expect(source.contains('truck.gif'), isFalse,
          reason: "Le camion animé décoratif doit avoir disparu.");
    });

    test("s'actualise en glissant, y compris à vide", () {
      expect(source.contains('onRefresh: _rafraichir'), isTrue,
          reason: "Le glisser n'est pas branché sur la liste.");
      expect(source.contains('RefreshIndicator('), isTrue,
          reason: "L'état vide doit rester rafraîchissable.");
      expect(source.contains('chargerVehicule(sansLoader: true)'), isTrue,
          reason: "Le voile de chargement masquerait ce qu'on vient de tirer.");
    });
  });

  group("L'écran des livraisons", () {
    // Taper une tuile d'accueil pose une DEMANDE d'onglet, qui survit a
    // l'essai : sans ce nettoyage, l'ecran monte ici l'appliquerait et
    // s'ouvrirait sur « Effectuee » au lieu du premier onglet.
    setUp(() => ongletLivraisonDemande = null);

    tearDown(() {
      allerAOngletLivraison = null;
      ongletLivraisonDemande = null;
    });

    testWidgets("s'ouvre sur l'onglet demandé depuis l'accueil", (tester) async {
      await tester.pumpWidget(ecran(const LivraisonScreen()));
      await tester.pump();

      final onglets = tester
          .widget<TabBarView>(find.byType(TabBarView))
          .controller!;

      expect(onglets.index, 0, reason: "On arrive sur le premier onglet.");
      expect(allerAOngletLivraison, isNotNull,
          reason: "L'écran doit se rendre pilotable depuis l'accueil, sans "
              "quoi la tuile « EFFECTUÉES » ouvrirait toujours le premier "
              "onglet.");

      // Le geste de l'accueil, sans passer par l'accueil.
      allerAOngletLivraison!(ONGLET_LIVRAISON_EFFECTUEE);
      await tester.pumpAndSettle();

      expect(onglets.index, ONGLET_LIVRAISON_EFFECTUEE,
          reason: "L'onglet demandé ne s'est pas ouvert.");
    });
  });
}
