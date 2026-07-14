import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mon_gravier_com/globale.dart';

import '../../../components/product_card.dart';
import '../../../models/ConfigModel.dart';
import '../../details/details_screen.dart';
import '../../products/products_screen.dart';
import 'section_title.dart';

class PopularProducts extends StatelessWidget {
  List<Produits> produits = [];
  PopularProducts({super.key, required this.produits});

  // @override
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: SectionTitle(
            showVoirPlus: true,
            title: "Produits populaires",
            press: () {
              Navigator.pushNamed(context, ProductsScreen.routeName);
            },
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              ...List.generate(
                produits.length,
                (index) {
                  return Padding(
                      padding: const EdgeInsets.only(left: 20),
                      child: ProductCard(
                        product: produits[index],
                        onPress: () => Navigator.pushNamed(
                          context,
                          DetailsScreen.routeName,
                          arguments: ProductDetailsArguments(
                              product: produits[index]),
                        ), onLongPress: () {  },
                      ),
                    );// here by default width and height is 0
                },
              ),
              const SizedBox(width: 20),
            ],
          ),
        )
      ],
    );
  }
}
