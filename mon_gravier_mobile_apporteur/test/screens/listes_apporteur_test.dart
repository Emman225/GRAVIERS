import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mon_gravier_com_apporteur/components/carte_operation.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_filleule.dart';
import 'package:mon_gravier_com_apporteur/screens/commission/commission_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/filleule/filleule_screen.dart';
import 'package:mon_gravier_com_apporteur/theme.dart';

/// LES LISTES DE L'APPORTEUR : MÊME CARTE, MÊME GESTE, CLAVIER AU REPOS.
///
/// Trois défauts que ni l'analyse ni la compilation ne voient :
///
///  · les listes ne se rafraîchissaient qu'en tapant une icône de la barre du
///    haut : le glisser, geste que tout le monde essaie d'abord, ne faisait
///    rien — et une liste VIDE, celle qu'on veut justement recharger, n'avait
///    même pas cette icône sous les yeux ;
///  · les cartes alignaient trois couleurs par ligne autour d'une image animée
///    décorative, et la commission — la seule chose qu'on vient chercher — n'y
///    était pas plus visible que le reste ;
///  · `SearchableList` ouvre le clavier D'OFFICE : il masquait la moitié de la
///    liste dès l'arrivée, et il fallait le refermer pour lire.
void main() {
  // `formaterDate` demande les symboles français. L'application les reçoit du
  // `GlobalMaterialLocalizations` de son `MaterialApp` ; l'essai doit les
  // charger, sans quoi chaque carte se remplace par un rectangle d'erreur.
  setUpAll(() async {
    await initializeDateFormatting('fr_FR', null);
  });

  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: Scaffold(body: enfant),
        ),
      );

  group("La carte d'une commission", () {
    testWidgets("prend la carte partagée et garde tout ce qu'elle disait",
        (tester) async {
      final c = UneCommission(
        id: 12,
        montant: 15000,
        montantTotal: 300000,
        clientId: 7,
        nom: 'KOUASSI',
        prenom: 'Yao',
        typeAffaire: 'VENTE',
        createdAt: '2026-08-20 09:00:00',
      );

      await tester.pumpWidget(ecran(carteCommission(c)));
      await tester.pump();

      expect(find.byType(CarteOperation), findsOneWidget,
          reason: "La liste doit utiliser la carte des paiements de l'accueil.");

      for (final attendu in ['KOUASSI', 'Yao', 'Total', 'VENTE']) {
        expect(find.textContaining(attendu), findsWidgets,
            reason: "« $attendu » a disparu de la carte.");
      }
    });

    testWidgets("signale une affaire non retrouvée sans crier à l'anomalie",
        (tester) async {
      // La commission est DUE même quand la chaîne vers le client est rompue :
      // on le signale, on ne la masque pas, et on n'invente pas de montant.
      final c = UneCommission(id: 3, montant: 5000, createdAt: '2026-08-20');

      await tester.pumpWidget(ecran(carteCommission(c)));
      await tester.pump();

      expect(find.textContaining('Filleul non identifié'), findsOneWidget);
      expect(find.textContaining('À vérifier'), findsOneWidget);
      expect(find.textContaining('Total'), findsNothing,
          reason: "Annoncer « 0 F » serait un chiffre faux, pas une absence.");
    });
  });

  group("La carte d'un filleul", () {
    testWidgets("prend la carte partagée, sans inventer de montant",
        (tester) async {
      final f = Filleule(
        id: 4,
        nom: 'DIALLO',
        prenom: 'Awa',
        contact1: '0700000000',
        email: 'awa@example.ci',
        typeClient: 'PARTICULIER',
        clientATerme: true,
      );

      await tester.pumpWidget(ecran(carteFilleul(f, onTap: () {})));
      await tester.pump();

      expect(find.byType(CarteOperation), findsOneWidget);

      for (final attendu in [
        'DIALLO',
        'Awa',
        '0700000000',
        'awa@example.ci',
        'PARTICULIER',
        'Client à terme',
      ]) {
        expect(find.textContaining(attendu), findsWidgets,
            reason: "« $attendu » a disparu de la carte.");
      }

      // Un filleul n'a pas de montant : la carte ne doit pas en afficher un.
      expect(find.textContaining('FCFA'), findsNothing,
          reason: "« 0 F » serait un chiffre faux plutôt qu'une absence.");
    });

    testWidgets("mène à ses paiements au toucher", (tester) async {
      int touches = 0;

      await tester.pumpWidget(
          ecran(carteFilleul(Filleule(id: 4, nom: 'DIALLO'),
              onTap: () => touches++)));
      await tester.pump();

      await tester.tap(find.byType(CarteOperation));
      await tester.pump();

      expect(touches, 1,
          reason: "L'action doit être portée par `CarteOperation.onTap` : un "
              "InkWell muni d'une action absorbe le geste, et un "
              "GestureDetector posé autour ne serait jamais appelé.");
    });
  });

  group("Le câblage des écrans", () {
    String source(String chemin) => File(chemin).readAsStringSync();

    test("les deux listes s'actualisent en glissant, y compris à vide", () {
      for (final chemin in [
        'lib/screens/commission/commission_screen.dart',
        'lib/screens/filleule/filleule_screen.dart',
      ]) {
        final s = source(chemin);
        // DEUX fois : une pour la liste, une pour l'état vide. Exiger une
        // seule présence laissait passer une liste débranchée.
        expect('onRefresh: _rafraichir'.allMatches(s).length, greaterThanOrEqualTo(2),
            reason: "$chemin : le glisser doit être branché sur la liste"
                " ET sur l'état vide.");
        expect(s.contains('RefreshIndicator('), isTrue,
            reason: "$chemin : l'état vide doit rester rafraîchissable.");
        expect(s.contains('sansLoader: true'), isTrue,
            reason: "$chemin : le voile de chargement masquerait ce qu'on "
                "vient de tirer pour voir.");
        expect(s.contains('AlwaysScrollableScrollPhysics'), isTrue,
            reason: "$chemin : une liste plus courte que l'écran ne défilerait "
                "pas, et le glisser n'atteindrait jamais l'indicateur.");
      }
    });

    test("les images animées décoratives ont disparu", () {
      expect(source('lib/screens/commission/commission_screen.dart')
          .contains('commande.gif'), isFalse);
      expect(source('lib/screens/filleule/filleule_screen.dart')
          .contains('user.gif'), isFalse);
    });

    test("AUCUN champ de recherche n'ouvre le clavier tout seul", () {
      // `SearchableList` met `autoFocusOnSearch` à VRAI par défaut : il faut
      // le refuser explicitement, écran par écran.
      final ecrans = Directory('lib')
          .listSync(recursive: true)
          .whereType<File>()
          .where((f) => f.path.endsWith('.dart'))
          .where((f) => f.readAsStringSync().contains('SearchableList<'));

      expect(ecrans, isNotEmpty, reason: "Aucun écran de liste trouvé.");

      for (final f in ecrans) {
        expect(f.readAsStringSync().contains('autoFocusOnSearch: false'), isTrue,
            reason: "${f.path} : le clavier s'ouvrira dès l'arrivée et "
                "masquera la moitié de la liste.");
      }
    });
  });

  group("Les messages restent sur leur ecran", () {
    /// Constate le 03/09/2026 : « Impossible de contacter le serveur »
    /// s'affichait sur un ecran qui n'avait rien demande. Ces ecrans lancent
    /// une requete des leur affichage ; si l'on change de page pendant
    /// qu'elle tourne, sa reponse revient alors qu'on est DEJA AILLEURS.
    const ecransQuiChargentALArrivee = [
      'lib/screens/home/home_screen.dart',
      'lib/screens/commission/commission_screen.dart',
      'lib/screens/filleule/filleule_screen.dart',
      'lib/screens/filleule/paiements/paiement_screen.dart',
      'lib/screens/demande_retrait/liste_demande_retrait_screen.dart',
    ];

    test("aucun message ne part d'un ecran deja quitte", () {
      final fautifs = <String>[];

      for (final chemin in ecransQuiChargentALArrivee) {
        final lignes = File(chemin).readAsLinesSync();
        for (var i = 0; i < lignes.length; i++) {
          if (!RegExp(r'^\s*afficher(Erreur|Info)\(').hasMatch(lignes[i])) {
            continue;
          }
          if (!lignes[i].contains('if (mounted)')) {
            fautifs.add('$chemin:${i + 1}');
          }
        }
      }

      expect(fautifs, isEmpty,
          reason: "Ces messages partiront sur l ecran ou l utilisateur se "
              "trouve au moment de la reponse : ${fautifs.join(', ')}");
    });

    test("aucun ecran n'est redessine apres avoir ete quitte", () {
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
          reason: "`setState` sur un ecran detruit leve une exception que le "
              "cadre avale : ${fautifs.join(', ')}");
    });
  });
}
