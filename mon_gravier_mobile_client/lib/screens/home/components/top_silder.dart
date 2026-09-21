import 'package:flutter/material.dart';
import 'package:carousel_slider/carousel_slider.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/screens/home/components/special_offers.dart';
import 'package:mon_gravier_com/screens/products/products_screen.dart';

import '../../../constants.dart';

/// CARROUSEL DE TÊTE.
///
/// Les points de position étaient écrits puis mis en commentaire : le carrousel
/// tournait tout seul sans que rien n'indique combien de bannières existaient
/// ni où l'on se trouvait. Ils sont rétablis, mais dessinés correctement —
/// l'ancienne version leur donnait 40 px de haut pour 10 de large dans un
/// `BoxShape.circle`, ce qui ne pouvait pas produire un point rond.
///
/// Sans bannière, le bloc disparaît au lieu de laisser 160 px de vide.
class TopSlider extends StatefulWidget {
  final List<Bannieres> items;

  const TopSlider({super.key, required this.items});

  @override
  State<TopSlider> createState() => _TopSliderState();
}

class _TopSliderState extends State<TopSlider> {
  int _courant = 0;

  @override
  Widget build(BuildContext context) {
    if (widget.items.isEmpty) return const SizedBox.shrink();

    final double largeur =
        MediaQuery.of(context).size.width - (kSpaceXl * 2);

    return Padding(
      padding: const EdgeInsets.only(top: kSpaceMd),
      child: Column(
        children: [
          CarouselSlider(
            items: [
              for (final banniere in widget.items)
                SpecialOfferCard(
                  image: banniere.image.toString(),
                  category: banniere.titre.toString(),
                  sousTitre: banniere.sousTitre.toString(),
                  online: true,
                  numOfBrands: 0,
                  myHeight: 168,
                  myWidth: largeur,
                  // Une bannière TOP ouvre les produits les mieux notés (09/09/2026).
                  press: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ProductsScreen(
                        titre: (banniere.titre ?? '').trim().isEmpty ? 'Nos meilleurs produits' : banniere.titre!.trim(),
                        selection: 'top',
                      ),
                    ),
                  ),
                ),
            ],
            options: CarouselOptions(
              height: 168,
              viewportFraction: 1,
              autoPlay: widget.items.length > 1,
              autoPlayInterval: const Duration(seconds: 5),
              autoPlayAnimationDuration: const Duration(milliseconds: 600),
              autoPlayCurve: Curves.easeInOutCubic,
              scrollDirection: Axis.horizontal,
              onPageChanged: (index, reason) {
                if (!mounted) return;
                setState(() => _courant = index);
              },
            ),
          ),
          if (widget.items.length > 1) ...[
            const SizedBox(height: kSpaceMd),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (int i = 0; i < widget.items.length; i++)
                  AnimatedContainer(
                    duration: kAnimationDuration,
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    height: 6,
                    width: _courant == i ? 18 : 6,
                    decoration: BoxDecoration(
                      color: _courant == i ? kPrimaryColor : kBorderColor,
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
