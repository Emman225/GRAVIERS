import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/carte_operation.dart';
import '../../../components/etat_vide.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../impression/impression_recu_paiement_pdf.dart';
import '../../../models/liste_paiement.dart';

class FactureListeScreen extends StatefulWidget {
  const FactureListeScreen(
      {super.key, required this.paiements, required this.total});

  final List<UnPaiement> paiements;
  final double total;

  @override
  State<FactureListeScreen> createState() => _FactureListeScreenState();
}

class _FactureListeScreenState extends State<FactureListeScreen> {
  // Les champs de sélection et de règlement — liste des moyens de paiement,
  // mode choisi, total sélectionné, identifiants cochés — ont disparu avec le
  // bouton « Payer factures » : cet écran ne règle plus rien.

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Écran de CONSULTATION.
      //
      // Il proposait de sélectionner des factures en attente puis de les régler
      // par mobile money. Décision de gestion du 11/08/2026 : les factures se
      // règlent en agence, et cet écran ne fait que les présenter — comme la
      // page « Mes paiements » du site, dont le bloc de paiement a été retiré
      // pour la même raison.
      //
      // Une facture déjà réglée reste ouvrable : c'est son reçu.
      body: Padding(
        padding: const EdgeInsets.fromLTRB(kSpaceLg, 0, kSpaceLg, kSpaceSm),
        child: SearchableList<UnPaiement>(
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
            icone: Icons.description_outlined,
            titre: "Aucun paiement",
            message: "Vos paiements apparaîtront ici, comme sur le site.",
          ),
          builder: (paiements, index, p) {
            final bool reglee = p.statut == 1;
            // « Mes paiements » du site (lot 89, 16/09/2026) : mêmes colonnes que
            // le web — code du paiement, moyen, n° d'affaire, montant, dates, état.
            final String affaire = p.numCommande ?? p.numero ?? '';
            final bool factureATerme = p.numCommande != null && p.numero != null && p.numero != p.numCommande;
            const libelles = {'COMMANDE': 'Commande', 'LOCATION': 'Location', 'LIVRAISON': 'Livraison'};
            final String nomAffaire = libelles[(p.service ?? '').toUpperCase()] ?? (p.service ?? 'Affaire');
            final String titreCarte = (!reglee && factureATerme)
                ? "Facture n° ${p.numero}"
                : "$nomAffaire n° $affaire";
            final String reference = (p.numeroRecu ?? '').isNotEmpty
                ? "reçu ${p.numeroRecu}"
                : ((p.codePaiement ?? '').isNotEmpty ? "paiement ${p.codePaiement}" : '');
            final String mentionCarte = reglee
                ? [p.modePaiement ?? '', reference].where((s) => s.isNotEmpty).join(' — ')
                : "À régler en agence"
                    "${(p.montantAPayer ?? 0) > (p.montant ?? 0) ? ' — reste sur ${formaterMontant(p.montantAPayer ?? 0)}' : ''}";
            final String etatCarte = p.etat ?? (reglee ? "Payé" : "En attente");
            final bool enCours = etatCarte.contains('attente') || etatCarte.contains('cours');

            return Padding(
              padding: const EdgeInsets.only(bottom: kSpaceMd),
              child: CarteOperation(
                icone: reglee
                    ? Icons.verified_outlined
                    : Icons.hourglass_bottom_outlined,
                numero: titreCarte,
                montant: formaterMontant(p.montant?.toDouble() ?? 0),
                mention: mentionCarte,
                date: "${p.datePaiement}",
                statut: etatCarte,
                couleurStatut: (reglee && !enCours) ? kSuccessColor : kWarningColor,
                fondStatut: (reglee && !enCours) ? kSuccessSoftColor : kWarningSoftColor,
                onTap: () async {
                  if (reglee) {
                    // Le reçu du RÈGLEMENT (paiement_id), pas de la facture.
                    Get.toNamed(ImpressionRecuPaiementPdf.routeName,
                        arguments: [2, "", p.paiementId ?? p.id]);
                  } else if (p.statut == 2) {
                    // Facture en attente : elle se règle au guichet. Le geste
                    // sélectionnait auparavant la facture en vue d'un paiement
                    // par mobile money ; il indique désormais la marche à suivre
                    // plutôt que de ne rien faire, ce qui aurait laissé croire à
                    // un écran qui ne répond pas.
                    afficherInfo(
                        "Cette facture se règle en agence. Présentez son numéro à nos guichets.");
                  }
                },
              ),
            );
          },
          initialList: widget.paiements,
          filter: (p0) {
            return widget.paiements
                .where((c) => (c.datePaiement.toString().contains(p0) ||
                    c.numero.toString().contains(p0) ||
                    (c.codePaiement ?? '').contains(p0) ||
                    (c.modePaiement ?? '').contains(p0) ||
                    c.montant.toString().contains(p0) ||
                    c.service.toString().contains(p0)))
                .toList();
          },
          inputDecoration: const InputDecoration(
            hintText: "Rechercher un paiement...",
            floatingLabelBehavior: FloatingLabelBehavior.never,
            prefixIcon: Icon(Icons.search, size: 20),
          ),
        ),
      ),

      // Le total restant était une simple ligne de texte vert centrée, sans
      // libellé ni séparation : on ne savait pas de quoi il était le total.
      bottomNavigationBar: (user.token != null && user.token != "")
          ? Container(
              decoration: const BoxDecoration(
                color: kSurfaceColor,
                border: Border(top: BorderSide(color: kBorderColor)),
              ),
              child: SafeArea(
                top: false,
                minimum: const EdgeInsets.fromLTRB(
                    kSpaceXl, kSpaceMd, kSpaceXl, kSpaceMd),
                child: Row(
                  children: [
                    const Expanded(
                      child: Text("Reste à régler", style: kLegendeStyle),
                    ),
                    Text(
                      formaterMontant(widget.total),
                      style: kMontantStyle,
                    ),
                  ],
                ),
              ),
            )
          : null,
    );
  }

  // Les méthodes _choixModePaiementForm() et obtenirLienPaiement() ont été
  // retirées avec le bouton de règlement : elles ouvraient le choix d'un
  // moyen mobile money puis appelaient la passerelle. Le règlement se fait
  // désormais au guichet.
}
