import 'package:flutter/material.dart';

import '../../../components/etat_vide.dart';
import '../../../components/product_card.dart';
import '../../../constants.dart';
import '../../../models/ConfigModel.dart';
import '../../details/details_screen.dart';
import '../../products/products_screen.dart';
import 'section_title.dart';

/// PRODUITS POPULAIRES, SUR L'ACCUEIL.
///
/// Trois corrections, aucune touchant au fond :
///
///  · la liste était déclarée `List<Produits> produits = []` NON FINALE dans un
///    widget immuable — l'analyseur le signalait à chaque exécution ;
///  · quand le catalogue n'était pas encore chargé, la rangée était vide et la
///    section apparaissait comme un titre suivi de rien ;
///  · les cartes se touchaient par la gauche uniquement, la dernière collée au
///    bord droit de l'écran.
class PopularProducts extends StatelessWidget {
  const PopularProducts({super.key, required this.produits});

  final List<Produits> produits;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          child: SectionTitle(
            showVoirPlus: true,
            title: "Produits populaires",
            press: () => Navigator.pushNamed(context, ProductsScreen.routeName),
          ),
        ),
        const SizedBox(height: kSpaceMd),

        // Pendant le chargement : trois trames grises à la place des cartes.
        // L'accueil garde sa forme au lieu de sauter quand les données
        // arrivent.
        if (produits.isEmpty)
          SizedBox(
            height: 230,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
              itemCount: 3,
              separatorBuilder: (_, __) => const SizedBox(width: kSpaceMd),
              itemBuilder: (_, __) => const SizedBox(
                width: 170,
                child: SqueletteCarteProduit(),
              ),
            ),
          )
        else
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
            physics: const BouncingScrollPhysics(),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (int index = 0; index < produits.length; index++)
                  Padding(
                    padding: EdgeInsets.only(
                        right: index == produits.length - 1 ? 0 : kSpaceMd),
                    child: ProductCard(
                      width: 170,
                      product: produits[index],
                      onPress: () => Navigator.pushNamed(
                        context,
                        DetailsScreen.routeName,
                        arguments:
                            ProductDetailsArguments(product: produits[index]),
                      ),
                      onLongPress: () {},
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}
