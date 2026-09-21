import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/constants.dart';

import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/details_livraison/details_livraison_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/carte_operation.dart';
import '../../../components/etat_vide.dart';
import '../../../globale.dart';

class LivraisonListeScreen extends StatelessWidget {
  const LivraisonListeScreen(
      {super.key, required this.livraisons, this.onRetour, this.onRafraichir});

  final List<UneLivraison> livraisons;

  /// Appelé au retour de l'écran de détail. Sans ce rechargement, la liste
  /// conservait l'objet AVANT acceptation : en revenant en arrière et en
  /// retapant la même carte, le bouton « Accepter livraison » réapparaissait et
  /// une seconde acceptation partait.
  final VoidCallback? onRetour;

  /// GLISSER DU HAUT VERS LE BAS POUR ACTUALISER.
  ///
  /// La liste ne se rafraîchissait qu'en tapant l'icône de la barre du haut :
  /// le geste que tout le monde essaie d'abord ne faisait rien.
  final Future<void> Function()? onRafraichir;

  /// L'état d'une livraison, en une couleur et un mot.
  ({IconData icone, String libelle, Color couleur, Color fond}) _etat(
      UneLivraison c) {
    switch (c.etatLivraison) {
      case LIVRAISON_LIVREE:
        return (
          icone: Icons.task_alt_outlined,
          libelle: "Terminée",
          couleur: kSuccessColor,
          fond: kSuccessSoftColor,
        );
      case LIVRAISON_EN_ATTENTE:
        return (
          icone: Icons.pending_actions_outlined,
          libelle: "En attente",
          couleur: kWarningColor,
          fond: kWarningSoftColor,
        );
      default:
        return (
          icone: Icons.local_shipping_outlined,
          libelle: "En cours",
          couleur: kPrimaryColor,
          fond: kPrimarySoftColor,
        );
    }
  }

  /// LE TITRE DE LA CARTE.
  ///
  /// Le bon d'enlèvement est ce que le livreur présente au fournisseur : c'est
  /// lui qu'il cherche des yeux quand il en a un. À défaut, le numéro de la
  /// livraison.
  String _titre(UneLivraison c) {
    // Le bon n'est montré qu'une fois la course ACCEPTÉE : avant, le livreur
    // n'a rien à présenter au fournisseur, et le code ne doit pas circuler.
    final be = (c.code_enlevement ?? '').trim();
    if (c.accepte == 1 && be.isNotEmpty && be != 'null') return "Bon d'enlèvement $be";

    // JAMAIS LE NUMÉRO DE LA COURSE (11/09/2026) : c'est le code de livraison,
    // que le CLIENT remet au livreur pour clore la course. Affiché ici, une
    // demande de livraison — qui n'a pas de bon d'enlèvement — le livrait au
    // livreur avant même que le client ne le donne. Le titre dit l'affaire.
    switch ((c.provenance ?? '').toString().toUpperCase()) {
      case 'LIVRAISON':
        return "Demande de livraison";
      case 'LOCATION':
        return "Location";
      case 'COMMANDE':
        return "Vente";
    }
    return "Livraison";
  }

  /// Ce qui ne tient pas dans le titre, mais qu'on ne peut pas perdre.
  List<String> _precisions(UneLivraison c) {
    final lignes = <String>[];

    void ajouter(String etiquette, Object? valeur) {
      final v = (valeur ?? '').toString().trim();
      if (v.isEmpty || v == 'null') return;
      lignes.add("$etiquette : $v");
    }

    if (c.etatLivraison != LIVRAISON_EN_ATTENTE) {
      ajouter("Fournisseur", c.nom_fournisseur);
      final client = (c.nomClient ?? '').toString().trim();
      final contact = (c.contactClient ?? '').toString().trim();
      if (client.isNotEmpty && client != 'null') {
        ajouter("Client",
            contact.isEmpty || contact == 'null' ? client : "$client - $contact");
      }
    }

    ajouter("Type", c.typeLivraison);

    if (c.etatLivraison != LIVRAISON_LIVREE) {
      ajouter("Lieu", c.lieuAffiche);
    } else {
      ajouter("Livrée le", formaterDate(c.updatedAt.toString()));
    }

    return lignes;
  }

  Widget _uneCarte(UneLivraison c) {
    final etat = _etat(c);

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: CarteOperation(
        icone: etat.icone,
        numero: _titre(c),
        montant: formaterMontant(c.coutLivraison?.toDouble() ?? 0),
        mention: "Livraison du ${formaterDate(c.dateLivraison.toString())}",
        lignes: _precisions(c),
        statut: etat.libelle,
        couleurStatut: etat.couleur,
        fondStatut: etat.fond,
        // L'ACTION EST PORTÉE ICI, ET NULLE PART AILLEURS. Un `InkWell` muni
        // d'une action absorbe le geste : un `GestureDetector` posé autour de
        // la carte ne serait jamais appelé. Voir carte_operation.dart.
        onTap: () async {
          // detailCommandeId est ABSENT pour une livraison issue d'une demande
          // de livraison (le ternaire prévoit d'ailleurs ce cas, niveau 2) :
          // le « ! » levait une exception avalée par le framework et la carte
          // ne réagissait tout simplement pas au toucher.
          await Get.toNamed(DetailsLivraisonScreen.routeName,
              arguments: [c, ((c.detailCommandeId ?? 0) > 0) ? 1 : 2, c.qte]);
          // Rechargement au retour : l'état de la livraison a pu changer.
          onRetour?.call();
        },
      ),
    );
  }

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.local_shipping_outlined,
    titre: "Aucune livraison ici",
    message: "Les livraisons de cet état apparaîtront dans cette liste.",
  );

  @override
  Widget build(BuildContext context) {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MÊME.
    //
    // `SearchableList` remplace toute la liste par l'état vide, et son
    // indicateur de rafraîchissement avec : sur un onglet sans livraison — le
    // cas le plus fréquent en début de journée — le glisser n'aurait rien
    // donné. On rend alors soi-même un contenu défilable.
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
      padding: const EdgeInsets.all(kSpaceLg),
      child: SearchableList<UneLivraison>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort),
        sortPredicate: (a, b) {
          String mtna = a.dateLivraison ?? '';
          String mtnb = b.dateLivraison ?? '';
          return mtna.compareTo(mtnb);
        },
        // `AlwaysScrollable` : sans elle, une liste plus courte que l'écran ne
        // défile pas, et le glisser n'atteint jamais l'indicateur.
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        onRefresh: onRafraichir,
        builder: (livraisons, index, c) => _uneCarte(c),
        emptyWidget: _vide,
        initialList: livraisons,
        filter: (p0) {
          return livraisons
              .where((c) => (c.code_enlevement.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                  c.adresse.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                  c.nom_fournisseur.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                  c.typeLivraison.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                  c.coutLivraison.toString().toUpperCase().contains(p0.trim().toUpperCase())))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}
