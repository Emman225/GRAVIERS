import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';
import 'package:mon_gravier_com/screens/details_livraison/details_livraison_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/codes_livraison.dart';
import '../../../components/etat_vide.dart';
import '../../../constants.dart';
import '../../../globale.dart';

/// LISTE DES LIVRAISONS D'UN ÉTAT.
///
/// La carte faisait 200 px de HAUT quel qu'en soit le contenu, et alignait six
/// lignes dans cinq couleurs différentes : numéro en ROUGE, montant en BLEU,
/// livreur en VERT, type en bleu nuit, adresse et date en noir. Aucune de ces
/// couleurs ne disait quoi que ce soit — et une adresse un peu longue sortait
/// de la carte.
///
/// Le contenu affiché, la recherche, le tri et la destination au clic sont
/// strictement identiques.
class LivraisonListeScreen extends StatelessWidget {
  const LivraisonListeScreen({super.key, required this.livraisons, this.onRafraichir});

  final List<UneLivraison> livraisons;

  /// GLISSER DU HAUT VERS LE BAS POUR ACTUALISER.
  ///
  /// Depuis que les onglets restent vivants, c'est la SEULE façon de remettre
  /// la liste à jour : changer d'onglet ne la recharge plus.
  final Future<void> Function()? onRafraichir;

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.local_shipping_outlined,
    titre: "Aucune livraison ici",
    message: "Les livraisons de cet état apparaîtront dans cette liste.",
  );

  @override
  Widget build(BuildContext context) {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MÊME. `SearchableList` remplace
    // toute la liste par l'état vide, et son indicateur de rafraîchissement
    // avec — or une liste vide est justement celle qu'on veut recharger.
    if (livraisons.isEmpty) {
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
      child: SearchableList<UneLivraison>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort, color: kTextSecondaryColor),
        sortPredicate: (a, b) {
          String mtna = a.dateLivraison ?? '';
          String mtnb = b.dateLivraison ?? '';
          return mtna.compareTo(mtnb);
        },
        // Sans `AlwaysScrollable`, une liste plus courte que l'écran ne défile
        // pas, et le glisser n'atteint jamais l'indicateur.
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        onRefresh: onRafraichir,
        emptyWidget: _vide,
        builder: (livraisons, index, c) {
          // Le client vient chercher sa marchandise lui-même : c'est un code
          // d'enlèvement, pas un numéro de livraison.
          final bool enlevement = c.clientId == c.livreurId;

          return Padding(
            padding: const EdgeInsets.only(bottom: kSpaceMd),
            child: Material(
              color: kSurfaceColor,
              borderRadius: BorderRadius.circular(kRadiusMd),
              child: InkWell(
                borderRadius: BorderRadius.circular(kRadiusMd),
                onTap: () => Get.toNamed(
                  DetailsLivraisonScreen.routeName,
                  arguments: [c, (c.detailCommandeId! > 0) ? 1 : 2, c.qte],
                ),
                child: Ink(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(kRadiusMd),
                    border: Border.all(color: kBorderColor),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(kSpaceLg),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 42,
                              height: 42,
                              decoration: BoxDecoration(
                                color: kPrimarySoftColor,
                                borderRadius: BorderRadius.circular(kRadiusSm),
                              ),
                              alignment: Alignment.center,
                              child: Icon(
                                enlevement
                                    ? Icons.storefront_outlined
                                    : Icons.local_shipping_outlined,
                                size: 20,
                                color: kPrimaryColor,
                              ),
                            ),
                            const SizedBox(width: kSpaceMd),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    enlevement
                                        ? "Enlèvement ${c.code_enlevement}"
                                        : "Livraison n° ${c.numero}",
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: kTextColor,
                                      height: 1.25,
                                    ),
                                  ),
                                  const SizedBox(height: kSpaceXs),
                                  // Le prix de la livraison ne s'affiche plus
                                  // ici (08/09/2026) : la date prévue le remplace.
                                  Text(
                                    (c.dateLivraison ?? '').trim().isNotEmpty
                                        ? "Prévue le ${formaterDate(c.dateLivraison!, format: 'd MMMM y')}"
                                        : (enlevement ? "Retrait chez le fournisseur" : "Livraison"),
                                    style: kCorpsSecondaireStyle,
                                  ),
                                ],
                              ),
                            ),
                            const Icon(Icons.chevron_right,
                                size: 20, color: kTextMutedColor),
                          ],
                        ),
                        // Les codes à remettre : copiables, partageables par
                        // WhatsApp. Le code de livraison n'a de sens que si un
                        // livreur vient ; pour un retrait sur place, seul le bon
                        // d'enlèvement compte.
                        // Le bon d'enlèvement n'est montré au client que s'il
                        // va lui-même chez le fournisseur ; sinon c'est le
                        // livreur qui le porte, et le client n'en a pas l'usage.
                        CodesLivraison(
                          codeLivraison: enlevement ? null : c.numero,
                          codeEnlevement: enlevement ? c.code_enlevement : null,
                        ),
                        const SizedBox(height: kSpaceMd),
                        const Divider(height: 1, color: kBorderColor),
                        const SizedBox(height: kSpaceMd),
                        _Ligne(
                          icone: Icons.person_outline,
                          texte: "${c.nomLivreur} — ${c.contactLivreur}",
                        ),
                        _Ligne(
                          icone: Icons.inventory_2_outlined,
                          texte: "${c.typeLivraison}",
                        ),
                        _Ligne(
                          icone: Icons.place_outlined,
                          texte: "${c.adresse}",
                        ),
                        _Ligne(
                          icone: Icons.schedule_outlined,
                          texte: formaterDate(c.dateLivraison.toString()),
                          dernier: true,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          );
        },
        initialList: livraisons,
        filter: (p0) {
          return livraisons
              .where((c) => (c.etatLivraison.toString().contains(p0) ||
                  c.adresse.toString().contains(p0) ||
                  c.nomLivreur.toString().contains(p0) ||
                  c.typeLivraison.toString().contains(p0) ||
                  c.coutLivraison.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher une livraison...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}

/// Une information de la carte : pictogramme + texte, sur une seule ligne.
class _Ligne extends StatelessWidget {
  const _Ligne({
    required this.icone,
    required this.texte,
    this.dernier = false,
  });

  final IconData icone;
  final String texte;
  final bool dernier;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: dernier ? 0 : kSpaceSm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icone, size: 15, color: kTextMutedColor),
          const SizedBox(width: kSpaceSm),
          Expanded(
            child: Text(
              texte,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: kCorpsSecondaireStyle,
            ),
          ),
        ],
      ),
    );
  }
}
