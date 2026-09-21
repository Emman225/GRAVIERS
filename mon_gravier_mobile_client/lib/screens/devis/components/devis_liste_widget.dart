import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:mon_gravier_com/screens/details_devis/details_devis_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/carte_operation.dart';
import '../../../components/etat_vide.dart';
import '../../../constants.dart';
import '../../../globale.dart';

/// LISTE DES DEVIS ENREGISTRÉS.
///
/// Même contenu, même recherche, même tri, même destination au clic — et le
/// rafraîchissement au retour est conservé tel quel.
class DevisListeWidget extends StatefulWidget {
  const DevisListeWidget({super.key, required this.devis});

  final List<DataDevis> devis;

  @override
  State<DevisListeWidget> createState() => _DevisListeWidgetState();
}

class _DevisListeWidgetState extends State<DevisListeWidget> {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(kSpaceLg, 0, kSpaceLg, kSpaceSm),
      child: SearchableList<DataDevis>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort, color: kTextSecondaryColor),
        sortPredicate: (a, b) {
          double mtna = a.montant ?? 0;
          double mtnb = b.montant ?? 0;
          return mtna.compareTo(mtnb);
        },
        physics: const BouncingScrollPhysics(),
        emptyWidget: const EtatVide(
          compact: true,
          icone: Icons.request_quote_outlined,
          titre: "Aucun devis enregistré",
          message:
              "Constituez un panier, puis enregistrez-le en devis pour le "
              "retrouver ici.",
        ),
        builder: (devis, index, c) => Padding(
          padding: const EdgeInsets.only(bottom: kSpaceMd),
          child: CarteOperation(
            icone: Icons.request_quote_outlined,
            numero: "Devis n° ${c.numero}",
            montant: formaterMontant(c.montant?.toDouble() ?? 0),
            // Un devis sans libellé affichait le mot « null » sous son montant :
            // l'interpolation d'une valeur absente écrit sa représentation
            // textuelle. On n'affiche donc que ce qui existe vraiment.
            mention: (c.libelle ?? '').trim().isEmpty ? '' : c.libelle!.trim(),
            date: "${c.dateDevis}",
            onTap: () async {
              await Get.toNamed(DetailsDevisScreen.routeName, arguments: c);
              // Un devis transformé en commande depuis l'écran de détail
              // peut renvoyer l'utilisateur ailleurs qu'ici : au retour,
              // cette liste n'existe alors plus.
              if (!mounted) return;
              setState(() {});
            },
          ),
        ),
        initialList: widget.devis,
        filter: (p0) {
          return widget.devis
              .where((c) => (c.dateDevis.toString().contains(p0) ||
                  c.libelle.toString().contains(p0) ||
                  c.montant.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher un devis...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}
