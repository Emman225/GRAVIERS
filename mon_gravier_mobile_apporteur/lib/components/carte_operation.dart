import 'package:flutter/material.dart';

import '../constants.dart';

/// CARTE D'UNE OPÉRATION : commande, location, livraison, devis, facture.
///
/// Les listes partageaient la même carte, recopiée d'un écran à l'autre, et
/// elle posait quatre problèmes :
///
///  · une hauteur FIXE de 120 px — au-delà de quatre lignes, ou avec la police
///    système agrandie, le contenu était coupé ;
///  · une image animée (`commande.gif`) de 80 px, décorative, occupant le tiers
///    de la carte et redécodée à chaque défilement ;
///  · le numéro en ROUGE, le montant en BLEU, le mode de paiement en NOIR gras
///    et la date en noir maigre : quatre couleurs pour quatre informations,
///    dont aucune ne signalait quoi que ce soit — le rouge en particulier
///    laissait croire à une anomalie ;
///  · aucun repère d'état, sauf pour les commandes terminées.
///
/// Ici : une seule couleur pour l'état, un montant dominant, et le reste en
/// gris. La hauteur suit le contenu.
class CarteOperation extends StatelessWidget {
  const CarteOperation({
    super.key,
    required this.numero,
    this.montant,
    this.onTap,
    this.montantBarre,
    this.mention,
    this.lignes,
    this.date,
    this.statut,
    this.couleurStatut = kPrimaryColor,
    this.fondStatut = kPrimarySoftColor,
    this.icone = Icons.receipt_long_outlined,
  });

  final String numero;
  /// Absent quand la ligne n'en porte pas : un filleul n'a pas de montant,
  /// et « 0 F » serait un chiffre faux plutot qu'une absence.
  final String? montant;

  /// Montant AVANT remise, barré. Absent s'il n'y a pas de remise.
  final String? montantBarre;

  /// Mode de paiement, adresse, ou toute précision d'une ligne.
  final String? mention;

  /// Precisions supplementaires, une par ligne. Les vides sont ignorees.
  /// Absentes sur l'accueil : la carte y garde exactement son rendu.
  final List<String>? lignes;

  final String? date;

  /// Libellé d'état — « Terminée », « En cours »... Absent, aucune pastille.
  final String? statut;
  final Color couleurStatut;
  final Color fondStatut;

  final IconData icone;

  /// Action au toucher. `null` rend la carte non cliquable — et VISIBLEMENT
  /// non cliquable, l'InkWell ne réagissant plus.
  ///
  /// ATTENTION : cette action doit être portée ICI, et JAMAIS réservée par un
  /// rappel vide. Un InkWell muni d'une action, fût-elle sans effet, absorbe le
  /// geste : un `GestureDetector` placé autour de la carte ne serait alors
  /// jamais appelé. C'est ainsi que trois écrans sont devenus muets.
  /// Voir `test/components/carte_operation_cliquable_test.dart`.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: kSurfaceColor,
      borderRadius: BorderRadius.circular(kRadiusMd),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(kRadiusMd),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(kRadiusMd),
            border: Border.all(color: kBorderColor),
          ),
          child: Padding(
            padding: const EdgeInsets.all(kSpaceLg),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: fondStatut,
                    borderRadius: BorderRadius.circular(kRadiusSm),
                  ),
                  alignment: Alignment.center,
                  child: Icon(icone, size: 20, color: couleurStatut),
                ),
                const SizedBox(width: kSpaceMd),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Flexible(
                            flex: 2,
                            child: Text(
                              numero,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: kTextColor,
                                height: 1.25,
                              ),
                            ),
                          ),
                          if (statut != null && statut!.isNotEmpty) ...[
                            const SizedBox(width: kSpaceSm),
                            // La pastille d'état N'ÉTAIT PAS contrainte : un
                            // libellé long — « EN ATTENTE DE TRAITEMENT » —
                            // débordait la carte de plus de cent pixels sur un
                            // écran de 320 px de large. Elle se coupe désormais
                            // plutôt que de pousser la ligne.
                            Flexible(
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: kSpaceSm, vertical: 3),
                                decoration: BoxDecoration(
                                  color: fondStatut,
                                  borderRadius:
                                      BorderRadius.circular(kRadiusPill),
                                ),
                                child: Text(
                                  statut!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: kEtiquetteStyle.copyWith(
                                      color: couleurStatut, fontSize: 10),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      if ((montant ?? '').trim().isNotEmpty) ...[
                      const SizedBox(height: kSpaceSm),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.baseline,
                        textBaseline: TextBaseline.alphabetic,
                        children: [
                          Flexible(
                            child: Text(
                              montant!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: kMontantStyle.copyWith(fontSize: 17),
                            ),
                          ),
                          if (montantBarre != null) ...[
                            const SizedBox(width: kSpaceSm),
                            Flexible(
                              child: Text(
                                montantBarre!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: kTextMutedColor,
                                  decoration: TextDecoration.lineThrough,
                                  decorationColor: kTextMutedColor,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      ],
                      if (mention != null && mention!.trim().isNotEmpty) ...[
                        const SizedBox(height: kSpaceXs),
                        Text(
                          mention!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: kCorpsSecondaireStyle,
                        ),
                      ],
                      if (lignes != null)
                        for (final l in lignes!)
                          if (l.trim().isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              l,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: kCorpsSecondaireStyle,
                            ),
                          ],
                      if (date != null && date!.trim().isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(date!, style: kLegendeStyle),
                      ],
                    ],
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.only(left: kSpaceSm, top: kSpaceMd),
                  child:
                      Icon(Icons.chevron_right, size: 20, color: kTextMutedColor),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
