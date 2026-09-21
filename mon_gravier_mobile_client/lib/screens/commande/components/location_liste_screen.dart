import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/details_location/details_location_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/carte_operation.dart';
import '../../../components/etat_vide.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/Commande.dart';

/// LISTE DES LOCATIONS D'UN ÉTAT (en attente, en cours, terminée).
///
/// Même contenu, même tri, même recherche, même destination au clic. Seules
/// changent la carte et la réaction à une liste vide.
class LocationListeScreen extends StatelessWidget {
  const LocationListeScreen({super.key, required this.locations});

  final List<DetailsLocation> locations;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceSm),
      child: SearchableList<DetailsLocation>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort, color: kTextSecondaryColor),
        sortPredicate: (a, b) {
          double mtna = a.montantTotal ?? 0;
          double mtnb = b.montantTotal ?? 0;
          return mtna.compareTo(mtnb);
        },
        physics: const BouncingScrollPhysics(),
        emptyWidget: const EtatVide(
          compact: true,
          icone: Icons.construction_outlined,
          titre: "Aucune location ici",
          message: "Les locations de cet état apparaîtront dans cette liste.",
        ),
        builder: (locations, index, c) {
          final bool estTerminee = c.etatLocation == LOCATION_TERMINE;
          final bool aUneRemise = c.remise != null && c.remise! > 0;

          return Padding(
            padding: const EdgeInsets.only(bottom: kSpaceMd),
            child: CarteOperation(
              icone: Icons.construction_outlined,
              numero: "Location n° ${c.numero}",
              montant: formaterMontant(c.montantTotal?.toDouble() ?? 0),
              montantBarre: aUneRemise
                  ? formaterMontant(
                      c.remise!.toDouble() + c.montantTotal!.toDouble())
                  : null,
              // Où en est le matériel (10/09/2026) : la livraison faite se lit
              // sur la carte, même si la location reste EN COURS.
              mention: [
                if ((c.etatLivraisonLibelle ?? '').isNotEmpty) c.etatLivraisonLibelle!,
                if (c.modePaiement != null) "Paiement : ${c.modePaiement}",
              ].join(" · "),
              date: formaterDate(c.dateLocation.toString()),
              statut: estTerminee ? "Terminée" : c.etatLocation,
              couleurStatut: estTerminee ? kSuccessColor : kAccentColor,
              fondStatut: estTerminee ? kSuccessSoftColor : kAccentSoftColor,
              onTap: () => Get.toNamed(
                DetailsLocationScreen.routeName,
                arguments: [c.id, c.etatLocation],
              ),
            ),
          );
        },
        initialList: locations,
        filter: (p0) {
          return locations
              .where((c) => (c.adresse.toString().contains(p0) ||
                  c.note.toString().contains(p0) ||
                  c.montantTotal.toString().contains(p0) ||
                  c.modePaiement.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher une location...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}
