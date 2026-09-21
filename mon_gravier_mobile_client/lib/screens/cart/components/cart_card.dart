import 'package:flutter/material.dart';

import '../../../components/compteur_quantite.dart';
import '../../../components/image_reseau.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/Cart.dart';

/// LIGNE D'ARTICLE DU PANIER.
///
/// Tout tenait sur une seule rangée : vignette de 50 px, nom, prix, sélecteur
/// de quantité et, pour une location, la durée. Sur un téléphone étroit, le
/// sélecteur de quantité écrasait le nom du produit — et une location affichait
/// « 3 Jour(s) » collé au bord droit, sans dire de quelle période il s'agissait.
///
/// La ligne est maintenant sur deux niveaux : ce qui est acheté en haut, la
/// quantité ou la durée en dessous. Les données affichées sont les mêmes.
class CartCard extends StatefulWidget {
  const CartCard({
    super.key,
    required this.cart,
    this.showCounter = true,
  });

  final Cart cart;
  final bool showCounter;

  @override
  State<CartCard> createState() => _CartCardState();
}

class _CartCardState extends State<CartCard> {
  bool get _estLocation => widget.cart.product.type_affaire == LOCATION;

  @override
  Widget build(BuildContext context) {
    final produit = widget.cart.product;
    final ancien = produit.aPrixPersonnalise ? produit.prixMoyen?.toDouble() : null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ImageReseau(
              url: produit.image.toString(),
              width: 56,
              height: 56,
              fit: BoxFit.cover,
              rayon: kRadiusSm,
              icone: Icons.photo_outlined,
            ),
            const SizedBox(width: kSpaceMd),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    produit.nom.toString(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: kTextColor,
                      height: 1.25,
                    ),
                  ),
                  const SizedBox(height: kSpaceXs),
                  Text(
                    "${formaterMontant(produit.prixEffectif.toDouble())} / ${produit.unite}",
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: kPrimaryColor,
                      fontFeatures: [FontFeature.tabularFigures()],
                    ),
                  ),
                  if (ancien != null)
                    Text(
                      formaterMontant(ancien),
                      style: const TextStyle(
                        fontSize: 12,
                        color: kTextMutedColor,
                        decoration: TextDecoration.lineThrough,
                        decorationColor: kTextMutedColor,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),

        // --------------------------------------------------------------
        // QUANTITÉ (vente) ou DURÉE (location)
        // --------------------------------------------------------------
        if (widget.showCounter) ...[
          const SizedBox(height: kSpaceMd),
          Row(
            children: [
              const Text("Quantité", style: kCorpsSecondaireStyle),
              const Spacer(),
              CompteurQuantite(
                valeurInitiale: widget.cart.numOfItem,
                minimum: 1,
                maximum: 1000,
                decimales: 1,
                pas: 0.1,
                couleur: kPrimaryColor,
                onChanged: (value) {
                  setState(() {
                    var val = value.toDouble().toStringAsFixed(1);
                    widget.cart.numOfItem = double.parse(val);
                  });
                },
              ),
            ],
          ),
        ] else if (_estLocation) ...[
          const SizedBox(height: kSpaceMd),
          Row(
            children: [
              const Icon(Icons.event_outlined,
                  size: 16, color: kTextMutedColor),
              const SizedBox(width: kSpaceSm),
              Expanded(
                child: Text(
                  "Location de ${widget.cart.nbreJours ?? 1} jour(s)"
                  "${(widget.cart.dateDebut ?? '').isEmpty ? '' : ' — du ${widget.cart.dateDebut} au ${widget.cart.dateDeFin}'}",
                  style: kCorpsSecondaireStyle,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
