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
    required this.montant,
    this.onTap,
    this.montantBarre,
    this.mention,
    this.date,
    this.statut,
    this.couleurStatut = kPrimaryColor,
    this.fondStatut = kPrimarySoftColor,
    this.icone = Icons.receipt_long_outlined,
    this.onSupprimer,
    this.actionSecondaire,
    this.iconeSecondaire = Icons.open_in_new,
    this.infoSecondaire,
    this.libelleSecondaire,
  });

  final String numero;
  final String montant;

  /// Montant AVANT remise, barré. Absent s'il n'y a pas de remise.
  final String? montantBarre;

  /// Mode de paiement, adresse, ou toute précision d'une ligne.
  final String? mention;
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

  /// Suppression depuis la liste (10/09/2026) : une corbeille à droite de la
  /// carte, à la place du chevron. Absent, la carte garde son chevron.
  final VoidCallback? onSupprimer;

  /// Une seconde action, à droite de la carte (lot 98, 16/09/2026) : par
  /// exemple ouvrir la page de vérification de la DGI d'une facture. Absente,
  /// le chevron habituel.
  final VoidCallback? actionSecondaire;
  final IconData iconeSecondaire;
  final String? infoSecondaire;

  /// Le mot du bouton (« DGI ») : un bouton bordé, lisible comme cliquable,
  /// et non une simple icône perdue dans la carte (retour du 16/09/2026).
  final String? libelleSecondaire;

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
                      const SizedBox(height: kSpaceSm),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.baseline,
                        textBaseline: TextBaseline.alphabetic,
                        children: [
                          Flexible(
                            child: Text(
                              montant,
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
                      if (mention != null && mention!.trim().isNotEmpty) ...[
                        const SizedBox(height: kSpaceXs),
                        Text(
                          mention!,
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
                if (onSupprimer != null)
                  Padding(
                    padding: const EdgeInsets.only(left: kSpaceXs),
                    child: IconButton(
                      onPressed: onSupprimer,
                      tooltip: 'Supprimer',
                      icon: const Icon(Icons.delete_outline,
                          size: 22, color: kErrorColor),
                    ),
                  )
                else if (actionSecondaire != null)
                  Padding(
                    padding: const EdgeInsets.only(left: kSpaceXs, top: kSpaceSm),
                    child: Tooltip(
                      message: infoSecondaire ?? '',
                      child: Material(
                        color: kPrimarySoftColor,
                        borderRadius: BorderRadius.circular(kRadiusMd),
                        child: InkWell(
                          onTap: actionSecondaire,
                          borderRadius: BorderRadius.circular(kRadiusMd),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 8),
                            decoration: BoxDecoration(
                              border: Border.all(color: kPrimaryColor, width: 1.2),
                              borderRadius: BorderRadius.circular(kRadiusMd),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(iconeSecondaire, size: 18, color: kPrimaryColor),
                                if ((libelleSecondaire ?? '').isNotEmpty) ...[
                                  const SizedBox(width: 4),
                                  Text(
                                    libelleSecondaire!,
                                    style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w700,
                                        color: kPrimaryColor),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  )
                else
                  const Padding(
                    padding: EdgeInsets.only(left: kSpaceSm, top: kSpaceMd),
                    child: Icon(Icons.chevron_right,
                        size: 20, color: kTextMutedColor),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
