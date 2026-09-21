import 'package:flutter/material.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';

import '../../../components/image_reseau.dart';
import '../../../constants.dart';

/// VISUELS DE LA FICHE PRODUIT.
///
/// Les vignettes RÉAGISSAIENT sans rien changer : le clic déplaçait le cadre de
/// sélection, mais l'image principale affichait toujours `widget.image`, quelle
/// que soit la vignette choisie. Le client cliquait sur une photo et voyait
/// la même image.
///
/// La vignette choisie s'affiche désormais réellement. L'ouverture de l'écran
/// reste identique : c'est l'image principale du produit qui est présentée
/// tant qu'aucune vignette n'a été touchée.
class ProductImages extends StatefulWidget {
  const ProductImages({
    super.key,
    required this.images,
    required this.image,
  });

  final String image;
  final List<ImageProduit> images;

  @override
  State<ProductImages> createState() => _ProductImagesState();
}

class _ProductImagesState extends State<ProductImages> {
  /// `null` = aucune vignette touchée, on montre l'image principale.
  int? selectedImage;

  String get _imageAffichee {
    final i = selectedImage;
    if (i == null || i < 0 || i >= widget.images.length) return widget.image;
    return widget.images[i].image.toString();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSpaceXl, kSpaceXl, kSpaceXl, 0),
      child: Column(
        children: [
          AspectRatio(
            aspectRatio: 1.4,
            child: Container(
              decoration: BoxDecoration(
                color: kSurfaceColor,
                borderRadius: BorderRadius.circular(kRadiusLg),
                border: Border.all(color: kBorderColor),
              ),
              clipBehavior: Clip.antiAlias,
              child: ImageReseau(
                url: _imageAffichee,
                fit: BoxFit.contain,
                icone: Icons.photo_outlined,
                fondPlaceholder: kSurfaceColor,
              ),
            ),
          ),
          if (widget.images.isNotEmpty) ...[
            const SizedBox(height: kSpaceMd),
            SizedBox(
              height: 56,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: widget.images.length,
                separatorBuilder: (_, __) => const SizedBox(width: kSpaceMd),
                itemBuilder: (context, index) => SmallProductImage(
                  isSelected: index == selectedImage,
                  press: () => setState(() => selectedImage = index),
                  image: widget.images[index].image.toString(),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class SmallProductImage extends StatelessWidget {
  const SmallProductImage({
    super.key,
    required this.isSelected,
    required this.press,
    required this.image,
  });

  final bool isSelected;
  final VoidCallback press;
  final String image;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: press,
      child: AnimatedContainer(
        duration: defaultDuration,
        height: 56,
        width: 56,
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(
          color: kSurfaceColor,
          borderRadius: BorderRadius.circular(kRadiusSm),
          // Le contour de sélection était dessiné en permanence, rendu
          // transparent quand la vignette n'était pas choisie : les vignettes
          // paraissaient donc flotter sans cadre.
          border: Border.all(
            color: isSelected ? kPrimaryColor : kBorderColor,
            width: isSelected ? 1.8 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: ImageReseau(
          url: image,
          fit: BoxFit.cover,
          icone: Icons.photo_outlined,
          fondPlaceholder: kSurfaceColor,
        ),
      ),
    );
  }
}
