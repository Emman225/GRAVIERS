import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';

import '../constants.dart';
import '../globale.dart';

class ProductCard extends StatelessWidget {
  const ProductCard({
    super.key,
    this.width = 200,
    this.aspectRetio = 1.02,
    required this.product,
    required this.onPress,
    required this.onLongPress,
  });

  final double width, aspectRetio;
  final Produits product;
  final VoidCallback onPress;
  final VoidCallback onLongPress;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: width,
      child: GestureDetector(
        onTap: onPress,
        onLongPress: onLongPress,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1.02,
              child: Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: kSecondaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Image.network(product.image.toString()),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              "${product.type_affaire} de ${product.nom}",
              style: const TextStyle(
                fontSize: 12,
                color: blackColor,
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  "${formaterMontant(product.prixEffectif.toDouble())}/${product.unite.toString()}",
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: kPrimaryColor,
                  ),
                ),
                addHorizontalSpace(3),
                if (product.aPrixPersonnalise)
                  Text(
                    "${formaterMontant(product.prixMoyen?.toDouble() ?? 0)}/${product.unite.toString()}",
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: redColor,
                      decoration: TextDecoration.lineThrough,
                    ),
                  )
                else if ((product.prixReduction ?? 0) > 0)
                  Text(
                    "${formaterMontant(product.prixReduction?.toDouble() ?? 0)}/${product.unite.toString()}",
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: redColor,
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
              ],
            )
          ],
        ),
      ),
    );
  }
}
