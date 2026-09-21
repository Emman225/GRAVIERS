import 'package:flutter/material.dart';
import 'package:mon_gravier_com/screens/products/products_screen.dart';

import '../../../components/image_reseau.dart';
import '../../../constants.dart';
import '../../../models/ConfigModel.dart';
import 'section_title.dart';

/// BANDEAUX PROMOTIONNELS DU MILIEU D'ACCUEIL.
///
/// La section s'affichait même sans aucune bannière : un titre suivi d'une
/// bande vide. Elle disparaît maintenant quand il n'y a rien à montrer.
///
/// Le titre portait par ailleurs une faute — « Specialement pour vous » — sur
/// l'écran le plus vu de l'application.
class SpecialOffers extends StatelessWidget {
  const SpecialOffers({super.key, required this.items});

  final List<Bannieres> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          child: SectionTitle(
            showVoirPlus: false,
            title: "Spécialement pour vous",
            press: () {},
          ),
        ),
        const SizedBox(height: kSpaceMd),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          physics: const BouncingScrollPhysics(),
          child: Row(
            children: [
              for (int i = 0; i < items.length; i++)
                Padding(
                  padding: EdgeInsets.only(
                      right: i == items.length - 1 ? 0 : kSpaceMd),
                  child: SpecialOfferCard(
                    image: items[i].image.toString(),
                    category: items[i].titre.toString(),
                    sousTitre: items[i].sousTitre.toString(),
                    online: true,
                    numOfBrands: 0,
                    // Une bannière FLASH ouvre les offres du moment (09/09/2026).
                    press: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => ProductsScreen(
                          titre: (items[i].titre ?? '').trim().isEmpty ? 'Offres flash' : items[i].titre!.trim(),
                          selection: 'flash',
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

/// VIGNETTE DE BANNIÈRE.
///
/// Le voile sombre partait du HAUT alors que Material place naturellement le
/// regard en bas d'une image : le texte se posait donc sur la partie la plus
/// chargée du visuel, et un sous-titre un peu long débordait sans être coupé.
///
/// Le dégradé part maintenant du bas, le texte s'y adosse, et titre comme
/// sous-titre sont bornés à une et deux lignes.
class SpecialOfferCard extends StatelessWidget {
  const SpecialOfferCard({
    super.key,
    required this.category,
    required this.image,
    required this.numOfBrands,
    required this.press,
    this.myWidth = 242,
    this.myHeight = 100,
    this.online = false,
    this.sousTitre = '',
  });

  final String category, image, sousTitre;
  final int numOfBrands;

  final double myWidth, myHeight;
  final GestureTapCallback press;
  final bool online;

  @override
  Widget build(BuildContext context) {
    final String secondeLigne =
        online ? sousTitre : "$numOfBrands références";

    return GestureDetector(
      onTap: press,
      child: SizedBox(
        width: myWidth,
        height: myHeight,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(kRadiusMd),
          child: Stack(
            fit: StackFit.expand,
            children: [
              online
                  ? ImageReseau(
                      url: image,
                      fit: BoxFit.cover,
                      icone: Icons.image_outlined,
                    )
                  : Image.asset(image, fit: BoxFit.cover),

              // Voile : assez dense en bas pour garantir la lisibilité du
              // texte blanc, transparent en haut pour ne pas ternir le visuel.
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                    colors: [
                      Color(0xE6101828),
                      Color(0x99101828),
                      Colors.transparent,
                    ],
                    stops: [0, 0.55, 1],
                  ),
                ),
              ),

              Positioned(
                left: kSpaceLg,
                right: kSpaceLg,
                bottom: kSpaceMd,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      category,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        height: 1.2,
                      ),
                    ),
                    if (secondeLigne.trim().isNotEmpty &&
                        secondeLigne != 'null') ...[
                      const SizedBox(height: 2),
                      Text(
                        secondeLigne,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 12,
                          height: 1.3,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
