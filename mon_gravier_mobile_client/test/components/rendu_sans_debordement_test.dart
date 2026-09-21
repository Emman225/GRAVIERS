import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/components/carte_operation.dart';
import 'package:mon_gravier_com/components/etat_vide.dart';
import 'package:mon_gravier_com/components/product_card.dart';
import 'package:mon_gravier_com/screens/home/components/categories.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/screens/cart/components/cart_card.dart';
import 'package:mon_gravier_com/theme.dart';
import 'package:searchable_listview/searchable_listview.dart';

/// RIEN NE DOIT DÉBORDER, MÊME SUR LE PLUS PETIT TÉLÉPHONE.
///
/// La refonte visuelle a changé les hauteurs : cartes de produit, cartes de
/// commande, états vides. Un débordement ne se voit pas à l'analyse statique —
/// il apparaît à l'exécution, sous la forme d'une bande jaune et noire en
/// travers de l'écran, et seulement sur les appareils où la place manque.
///
/// Ces essais rendent réellement les composants, dans les mêmes grilles que
/// les écrans, sur trois tailles d'écran dont un 320 x 568 (le plus petit
/// Android encore courant). En test, un débordement lève une exception que
/// `takeException()` révèle.
///
/// Ils vérifient aussi le cas qui casse tout : un libellé anormalement long.
void main() {
  /// Un catalogue réel comporte des noms courts ET des noms interminables.
  Produits produit({String? nom, int? personnalise}) => Produits(
        id: 1,
        reference: 'REF-0001',
        nom: nom ?? 'Gravier concassé 15/25',
        abreviation: 'GC',
        unite: 'Tonne',
        unite_id: 1,
        description: 'Gravier pour béton et fondations',
        prixMoyen: 22000,
        prixPersonnalise: personnalise?.toDouble(),
        prixReduction: 0,
        meilleurNote: 5,
        statut: 1,
        image: 'https://exemple.invalid/gravier.jpg',
        type_affaire: 'VENTE',
      );

  // Le thème réel de l'application : c'est lui qui fixe les tailles de police,
  // et donc les hauteurs. Un essai mené sous le thème par défaut de Material ne
  // prouverait rien.
  Widget ecran(Widget enfant) => Builder(
        builder: (contexte) => MaterialApp(
          theme: AppTheme.lightTheme(contexte),
          home: Scaffold(body: enfant),
        ),
      );

  /// Les trois tailles couvrent le parc réel : petit, standard, grand.
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

  group('Carte produit', () {
    testWidgets('tient dans la grille du catalogue', (tester) async {
      await surChaqueEcran(
        tester,
        () => GridView.builder(
          // Exactement la grille des écrans Produits, Catégorie, Recherche
          // et Liste de souhaits.
          gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
            maxCrossAxisExtent: 200,
            childAspectRatio: 0.7,
            mainAxisSpacing: 20,
            crossAxisSpacing: 16,
          ),
          itemCount: 6,
          itemBuilder: (context, index) => ProductCard(
            product: produit(),
            onPress: () {},
            onLongPress: () {},
          ),
        ),
      );
    });

    testWidgets('supporte un nom interminable et un prix barré',
        (tester) async {
      await surChaqueEcran(
        tester,
        () => GridView.builder(
          gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
            maxCrossAxisExtent: 200,
            childAspectRatio: 0.7,
            mainAxisSpacing: 20,
            crossAxisSpacing: 16,
          ),
          itemCount: 4,
          itemBuilder: (context, index) => ProductCard(
            product: produit(
              nom: 'Gravier concassé de rivière lavé calibre 15/25 '
                  'pour béton armé et fondations profondes',
              personnalise: 18500,
            ),
            onPress: () {},
            onLongPress: () {},
          ),
        ),
      );
    });

    testWidgets('tient dans la rangée horizontale de l\'accueil',
        (tester) async {
      await surChaqueEcran(
        tester,
        () => SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (int i = 0; i < 4; i++)
                ProductCard(
                  width: 170,
                  product: produit(),
                  onPress: () {},
                  onLongPress: () {},
                ),
            ],
          ),
        ),
      );
    });
  });

  group('Carte d\'opération', () {
    testWidgets('tient avec toutes ses lignes renseignées', (tester) async {
      await surChaqueEcran(
        tester,
        () => ListView(
          children: [
            for (int i = 0; i < 3; i++)
              const Padding(
                padding: EdgeInsets.all(16),
                child: CarteOperation(
                  numero: 'Commande n° 849677',
                  montant: '28 813 F',
                  montantBarre: '32 536 F',
                  mention: 'Paiement : Paiement en agence',
                  date: '25 août 2026 à 14h30',
                  statut: 'EN TRAITEMENT',
                  onTap: _rienAFaire,
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
            numero: 'Commande n° 8496770000112233445566778899',
            montant: '128 813 456 F',
            montantBarre: '132 536 789 F',
            mention:
                'Paiement : virement bancaire avec justificatif transmis au guichet',
            date: 'mercredi 25 août 2026 à 14 heures 30 minutes',
            statut: 'EN ATTENTE DE TRAITEMENT',
            onTap: _rienAFaire,
          ),
        ),
      );
    });
  });

  group('État vide', () {
    testWidgets('tient avec un message long et un bouton', (tester) async {
      await surChaqueEcran(
        tester,
        () => EtatVide(
          icone: Icons.inbox_outlined,
          titre: 'Votre panier est vide',
          message:
              'Parcourez le catalogue et ajoutez le sable, le gravier ou le '
              'matériel dont vous avez besoin pour votre chantier.',
          libelleAction: 'Voir le catalogue',
          action: () {},
        ),
      );
    });

    testWidgets('tient en version resserrée, sans bouton', (tester) async {
      await surChaqueEcran(
        tester,
        () => const EtatVide(
          compact: true,
          titre: 'Aucune commande ici',
          message: 'Les commandes de cet état apparaîtront dans cette liste.',
        ),
      );
    });
  });

  group('État vide dans une liste cherchable', () {
    // Six écrans passent un EtatVide au paramètre `emptyWidget` de
    // SearchableList : commandes, locations, livraisons, devis, factures et
    // demandes de livraison. Ce composant porte son propre défilement, et
    // SearchableList le rend dans une Column — une composition dont il faut
    // vérifier qu'elle tient, y compris sur le plus petit écran.
    //
    // C'est le SEUL endroit où l'état vide n'est pas posé directement dans le
    // corps d'un écran : si une composition devait céder, c'est celle-ci.
    testWidgets('ne réclame pas de hauteur infinie', (tester) async {
      await surChaqueEcran(
        tester,
        () => SearchableList<String>(
          // Exactement la configuration des six ecrans concernes.
          shrinkWrap: true,
          searchFieldEnabled: true,
          initialList: const <String>[],
          builder: (liste, index, element) => Text(element),
          filter: (terme) => const <String>[],
          emptyWidget: const EtatVide(
            compact: true,
            icone: Icons.receipt_long_outlined,
            titre: 'Aucune commande ici',
            message:
                'Les commandes de cet état apparaîtront dans cette liste.',
          ),
        ),
      );
    });
  });

  group('Blocs du catalogue', () {
    // « Les blocs de catalogue doivent etre identiques (taille, hauteur,
    // largeur) ». La ligne du prix barre n'existait que sur les produits
    // remises : le bloc d'une carte sans remise etait donc PLUS COURT que celui
    // de sa voisine.
    //
    // On mesure dans la rangee horizontale de l'accueil, ou la hauteur suit le
    // contenu. Dans la grille, la cellule impose deja sa hauteur : y mesurer la
    // carte reviendrait a mesurer la grille, et l'essai ne prouverait rien —
    // c'est l'erreur qu'a commise la premiere version de ce controle.
    testWidgets('ont tous la meme hauteur, remise ou non', (tester) async {
      tester.view.physicalSize = const Size(360, 900);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.reset);

      // Un melange volontaire : sans remise, avec prix personnalise, nom court,
      // nom interminable.
      final melange = [
        produit(),
        produit(personnalise: 18500),
        produit(nom: 'Sable'),
        produit(
          nom: 'Gravier concasse de riviere lave calibre 15/25 pour beton',
          personnalise: 19000,
        ),
      ];

      await tester.pumpWidget(ecran(
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final p in melange)
                ProductCard(
                  width: 170,
                  product: p,
                  onPress: () {},
                  onLongPress: () {},
                ),
            ],
          ),
        ),
      ));
      await tester.pump();

      expect(tester.takeException(), isNull);

      final hauteurs = find
          .byType(ProductCard)
          .evaluate()
          .map((e) => tester.getSize(find.byWidget(e.widget)).height)
          .toSet();

      expect(hauteurs.length, 1,
          reason: 'Toutes les cartes doivent avoir la meme hauteur, quel que '
              'soit leur contenu. Hauteurs trouvees : $hauteurs');
    });
  });

  group('Rangee de categories', () {
    // Les categories tenaient sur deux rangees de cinq, et celles au-dela de la
    // dixieme n'apparaissaient nulle part. Elles defilent maintenant sur une
    // rangee unique — qui ne doit pas deborder, meme avec des libelles longs.
    testWidgets('defile sur une ligne sans deborder', (tester) async {
      await surChaqueEcran(
        tester,
        () => CategoriesArticle(
          categories: [
            for (int i = 0; i < 12; i++)
              Categories(
                id: i,
                nom: i.isEven
                    ? 'Materiaux de construction et agregats'
                    : 'Sable',
                image: 'https://exemple.invalid/cat.png',
              ),
          ],
        ),
      );
    });
  });

  group('Ligne du panier', () {
    // Le compteur de quantite s'est elargi : il porte maintenant un champ de
    // saisie entre les deux boutons. La ligne du panier doit continuer de tenir
    // sur le plus petit telephone, libelle compris.
    testWidgets('tient avec le compteur saisissable', (tester) async {
      await surChaqueEcran(
        tester,
        () => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              child: CartCard(
                cart: Cart(
                  product: produit(
                    nom: 'Gravier concasse de riviere lave calibre 15/25',
                  ),
                  numOfItem: 12.5,
                  type: 1,
                ),
              ),
            ),
          ],
        ),
      );
    });
  });
}

void _rienAFaire() {}
