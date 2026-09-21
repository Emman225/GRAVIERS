import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../components/image_reseau.dart';
import '../../../constants.dart';
import '../../../models/ConfigModel.dart';
import '../../products/products_categorie_screen.dart';

/// RACCOURCIS PAR CATÉGORIE — UNE SEULE RANGÉE.
///
/// L'accueil appelait ce composant DEUX FOIS : cinq catégories sur une première
/// rangée, cinq de plus sur une seconde, et au-delà de dix, rien. Deux rangées
/// pour une même famille de raccourcis, coupées à un endroit arbitraire, sans
/// que rien n'indique que la suite existait.
///
/// Tout tient maintenant sur une rangée unique qui défile horizontalement :
/// aucune catégorie n'est perdue, quel qu'en soit le nombre, et la rangée ne
/// peut plus déborder — c'était le cas dès qu'un libellé était un peu long.
class CategoriesArticle extends StatelessWidget {
  final List<Categories> categories;

  const CategoriesArticle({super.key, required this.categories});

  @override
  Widget build(BuildContext context) {
    if (categories.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: kSpaceLg),
      child: SizedBox(
        height: 104,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          itemCount: categories.length,
          separatorBuilder: (_, __) => const SizedBox(width: kSpaceMd),
          itemBuilder: (context, index) => CategoryCard(
            icon: categories[index].image.toString(),
            text: categories[index].nom.toString(),
            press: () => Get.toNamed(
              ProductsCategorieScreen.routeName,
              arguments: [categories[index].id, categories[index].nom],
            ),
          ),
        ),
      ),
    );
  }
}

class CategoryCard extends StatelessWidget {
  const CategoryCard({
    super.key,
    required this.icon,
    required this.text,
    required this.press,
  });

  final String icon, text;
  final GestureTapCallback press;

  @override
  Widget build(BuildContext context) {
    // Largeur fixe : toutes les vignettes ont exactement la même, quelle que
    // soit la longueur du libellé. Une rangée de raccourcis de tailles
    // différentes ne se lit pas comme une rangée.
    return SizedBox(
      width: 76,
      child: InkWell(
        onTap: press,
        borderRadius: BorderRadius.circular(kRadiusMd),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 60,
              width: 60,
              decoration: BoxDecoration(
                color: kSurfaceColor,
                borderRadius: BorderRadius.circular(kRadiusMd),
                border: Border.all(color: kBorderColor),
              ),
              clipBehavior: Clip.antiAlias,
              padding: const EdgeInsets.all(kSpaceMd),
              child: ImageReseau(
                url: icon,
                fit: BoxFit.contain,
                icone: Icons.category_outlined,
                fondPlaceholder: kSurfaceColor,
              ),
            ),
            const SizedBox(height: kSpaceSm),
            Text(
              text,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 11,
                height: 1.2,
                fontWeight: FontWeight.w600,
                color: kTextSecondaryColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
