import 'package:flutter/material.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';

import '../constants.dart';
import '../globale.dart';
import 'image_reseau.dart';

/// CARTE PRODUIT.
///
/// Avant : une vignette grise sans contour, puis « VENTE de Sable lavé » sur
/// une seule ligne de 12 px, puis deux prix côte à côte de taille presque
/// identique — l'actuel en bleu, l'ancien barré en rouge. Rien ne disait lequel
/// des deux le client allait payer, et l'ensemble ne se lisait pas comme une
/// carte : c'était une colonne d'éléments posés les uns sous les autres.
///
/// Après : une vraie carte — fond blanc, contour franc, coins arrondis et une
/// ombre basse qui la décolle du fond —, la nature de l'offre en pastille sur
/// le visuel, le nom du produit lisible, et UN prix dominant.
///
/// Le comportement ne change pas : mêmes paramètres, mêmes rappels `onPress` et
/// `onLongPress`, mêmes données affichées.
class ProductCard extends StatelessWidget {
  const ProductCard({
    super.key,
    this.width = 200,
    this.aspectRetio = 1.25,
    required this.product,
    required this.onPress,
    required this.onLongPress,
  });

  final double width, aspectRetio;
  final Produits product;
  final VoidCallback onPress;
  final VoidCallback onLongPress;

  /// Prix affiché AVANT remise, s'il existe. Un seul des deux cas s'applique,
  /// comme dans la version d'origine.
  double? get _ancienPrix {
    if (product.aPrixPersonnalise) return product.prixMoyen?.toDouble();
    if ((product.prixReduction ?? 0) > 0) {
      return product.prixReduction?.toDouble();
    }
    return null;
  }

  bool get _estLocation => product.type_affaire == LOCATION;

  @override
  Widget build(BuildContext context) {
    final ancien = _ancienPrix;
    final unite = product.unite.toString();

    return SizedBox(
      width: width,
      // L'ombre est portée par un conteneur EXTÉRIEUR : le `Material` de la
      // carte est rogné à ses coins arrondis, et une ombre dessinée à
      // l'intérieur y serait coupée.
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(kRadiusMd),
          boxShadow: kShadowCarte,
        ),
        child: Material(
          color: kSurfaceColor,
          borderRadius: BorderRadius.circular(kRadiusMd),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onPress,
            onLongPress: onLongPress,
            child: Ink(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(kRadiusMd),
                // Contour FRANC : à 1 px dans un bleu très pâle, il n'était
                // visible sur aucun écran un peu lumineux — et les blocs du
                // catalogue paraissaient flotter sans limite.
                border: Border.all(color: kBorderFortColor, width: 1.2),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  // -------------------------------------------------------
                  // VISUEL
                  // -------------------------------------------------------
                  AspectRatio(
                    aspectRatio: aspectRetio,
                    child: DecoratedBox(
                      // FOND DU VISUEL.
                      //
                      // Les photos de matériel de location sont détourées sur
                      // blanc : posées sur une carte blanche, elles n'avaient
                      // plus aucune limite — la machine paraissait flotter dans
                      // le vide, et la vignette entière semblait vide.
                      //
                      // Un dégradé très clair, pris sur le bleu du logo, rend
                      // au visuel un fond et un bord.
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            kSurfaceColor,
                            // Le materiel de location est photographie sur
                            // fond blanc : sa vignette paraissait vide. Un fond
                            // un peu plus soutenu que celui de la vente lui
                            // rend un contour, et distingue au passage les deux
                            // types d'offre.
                            _estLocation ? kAccentSoftColor : kPrimarySoftColor,
                          ],
                        ),
                      ),
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          Padding(
                            padding: const EdgeInsets.all(kSpaceSm),
                            child: ImageReseau(
                              url: product.image.toString(),
                              // `contain` et non `cover` : une photo de
                              // bétonnière recadrée en carré perdait ses bords.
                              fit: BoxFit.contain,
                              icone: Icons.photo_outlined,
                              fondPlaceholder: Colors.transparent,
                            ),
                          ),
                          Positioned(
                            top: kSpaceSm,
                            left: kSpaceSm,
                            child: _Pastille(
                              texte: _estLocation ? "LOCATION" : "VENTE",
                              couleur:
                                  _estLocation ? kAccentColor : kPrimaryColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  // Filet de séparation entre le visuel et le texte : sans lui,
                  // le dégradé se fondait dans le blanc de la fiche.
                  const Divider(height: 1, thickness: 1, color: kBorderColor),

                  // -------------------------------------------------------
                  // TEXTE
                  // -------------------------------------------------------
                  Padding(
                    padding: const EdgeInsets.all(kSpaceMd),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Une seule ligne, coupée proprement : un nom long
                        // faisait auparavant grandir la carte et déborder la
                        // grille.
                        Text(
                          product.nom.toString(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: kTextColor,
                            height: 1.25,
                          ),
                        ),
                        const SizedBox(height: kSpaceSm),
                        // Le prix que le client paiera, seul en avant.
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Flexible(
                              child: Text(
                                formaterMontant(
                                    product.prixEffectif.toDouble()),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: kPrimaryColor,
                                  height: 1.1,
                                  fontFeatures: [FontFeature.tabularFigures()],
                                ),
                              ),
                            ),
                            Text(
                              "/$unite",
                              style: const TextStyle(
                                fontSize: 12,
                                color: kTextMutedColor,
                                height: 1.1,
                              ),
                            ),
                          ],
                        ),
                        // Hauteur RÉSERVÉE, avec ou sans prix barré : sans
                        // cela, une carte sans remise était plus courte que sa
                        // voisine et la grille présentait deux hauteurs de
                        // blocs.
                        const SizedBox(height: 2),
                        SizedBox(
                          height: 16,
                          child: ancien == null
                              ? null
                              : Text(
                                  "${formaterMontant(ancien)}/$unite",
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: kTextMutedColor,
                                    height: 1.3,
                                    decoration: TextDecoration.lineThrough,
                                    decorationColor: kTextMutedColor,
                                  ),
                                ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Étiquette posée sur le visuel : vente ou location, en un mot.
class _Pastille extends StatelessWidget {
  const _Pastille({required this.texte, required this.couleur});

  final String texte;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: kSpaceSm, vertical: 3),
      decoration: BoxDecoration(
        color: couleur,
        borderRadius: BorderRadius.circular(kRadiusPill),
      ),
      child: Text(
        texte,
        style: kEtiquetteStyle.copyWith(color: Colors.white, fontSize: 9),
      ),
    );
  }
}
