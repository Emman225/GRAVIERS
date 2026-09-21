import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/details_commande/details_commande_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/carte_operation.dart';
import '../../../components/etat_vide.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/Commande.dart';

/// LISTE DES COMMANDES D'UN ÉTAT (en attente, en traitement, terminée).
///
/// Le contenu et le comportement sont inchangés : même tri par montant, même
/// recherche sur l'adresse, la note, le montant et le mode de paiement, même
/// destination au clic avec les mêmes arguments.
///
/// Ce qui change : la carte (voir `CarteOperation`), et surtout le cas où la
/// liste est VIDE — un onglet sans commande n'affichait rien du tout, ce qui
/// ressemblait à un chargement bloqué.
class CommandeListeScreen extends StatelessWidget {
  const CommandeListeScreen({super.key, required this.commandes, this.onRafraichir});

  final List<DetailsCommande> commandes;

  /// GLISSER DU HAUT VERS LE BAS POUR ACTUALISER.
  ///
  /// Depuis que les onglets restent vivants, c'est la SEULE facon de
  /// remettre la liste a jour : changer d'onglet ne la recharge plus.
  final Future<void> Function()? onRafraichir;

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.receipt_long_outlined,
    titre: "Aucune commande ici",
    message: "Les commandes de cet état apparaîtront dans cette liste.",
  );

  @override
  Widget build(BuildContext context) {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MEME. `SearchableList`
    // remplace toute la liste par l'etat vide, et son indicateur de
    // rafraichissement avec — or une liste vide est justement celle
    // qu'on veut recharger.
    if (commandes.isEmpty) {
      return RefreshIndicator(
        color: kPrimaryColor,
        onRefresh: onRafraichir ?? () async {},
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics()),
          padding: const EdgeInsets.all(kSpaceLg),
          children: const [SizedBox(height: kSpaceXxl), _vide],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceSm),
      child: SearchableList<DetailsCommande>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort, color: kTextSecondaryColor),
        sortPredicate: (a, b) {
          double mtna = a.montantTotal ?? 0;
          double mtnb = b.montantTotal ?? 0;
          return mtna.compareTo(mtnb);
        },
        // Sans `AlwaysScrollable`, une liste plus courte que l'ecran ne
        // defile pas, et le glisser n'atteint jamais l'indicateur.
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        onRefresh: onRafraichir,
        emptyWidget: _vide,
        builder: (commandes, index, c) {
          final bool estTerminee = c.etatCommande == COMMANDE_TERMINE;
          final bool aUneRemise = c.remise != null && c.remise! > 0;

          return Padding(
            padding: const EdgeInsets.only(bottom: kSpaceMd),
            child: CarteOperation(
              icone: Icons.receipt_long_outlined,
              numero: "Commande n° ${c.numero}",
              montant: formaterMontant(c.montantTotal?.toDouble() ?? 0),
              montantBarre: aUneRemise
                  ? formaterMontant(
                      c.remise!.toDouble() + c.montantTotal!.toDouble())
                  : null,
              mention: c.modePaiement == null
                  ? null
                  : "Paiement : ${c.modePaiement}",
              date: formaterDate(c.dateCommande.toString()),
              statut: estTerminee ? "Terminée" : c.etatCommande,
              couleurStatut: estTerminee ? kSuccessColor : kPrimaryColor,
              fondStatut: estTerminee ? kSuccessSoftColor : kPrimarySoftColor,
              onTap: () => Get.toNamed(
                DetailsCommandeScreen.routeName,
                arguments: [c.id, c.etatCommande, c.remise, c.montantTotal],
              ),
            ),
          );
        },
        initialList: commandes,
        filter: (p0) {
          return commandes
              .where((c) => (c.adresse.toString().contains(p0) ||
                  c.note.toString().contains(p0) ||
                  c.montantTotal.toString().contains(p0) ||
                  c.modePaiement.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher une commande...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}
