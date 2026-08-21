
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';
import '../../../impression/impression_recu_paiement_pdf.dart';
import '../../../models/liste_paiement.dart';

class FactureListeScreen extends StatefulWidget {
  const FactureListeScreen({super.key, required this.paiements, required this.total});
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
      body: Container(
        width: double.infinity,
        height: heightOfScreen(context),
        decoration: const BoxDecoration(
          image: DecorationImage(
            image: AssetImage("assets/images/bg.jpg"),
            fit: BoxFit.cover,
            opacity: 0.1,
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(15.0),
          child: SearchableList<UnPaiement>(
            searchFieldEnabled: true,
            shrinkWrap: true,
            autoFocusOnSearch: false,
            sortWidget: const Icon(Icons.sort),
            sortPredicate: (a, b) {
              double mtna = a.montant ?? 0;
              double mtnb = b.montant ?? 0;
              return mtna.compareTo(mtnb);
            },
            physics: const BouncingScrollPhysics(),
            builder: (paiements, index, p) => GestureDetector(
              onTap: () async {
                if (p.statut == 1) {
                  //Paiement effectué on imprime le reçu de paiement
                  Get.toNamed(ImpressionRecuPaiementPdf.routeName,
                      arguments: [2, "", p.id]);
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
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: 160,
                  decoration: BoxDecoration(
                    color: Colors.grey[200],
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: mainSpaceBet,
                    children: [
                      const SizedBox(
                        width: 10,
                      ),
                      Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(30),
                            image: const DecorationImage(
                                image: AssetImage(
                                    "assets/images/attente.png"),
                                fit: BoxFit.cover,
                                opacity: 0.6),
                          ),
                          child: Container()),
                      const SizedBox(
                        width: 10,
                      ),
                      Flexible(
                        child: Column(
                          crossAxisAlignment:
                          CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              '# ${p.numero}',
                              style: const TextStyle(
                                color: Colors.red,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              "Total: ${formaterMontant(p.montant?.toDouble() ?? 0)}",
                              style: const TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              'Paiement de facture n° ${p.numero} pour ${p.service} effectué',
                              style: const TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '${p.datePaiement}',
                              style: const TextStyle(
                                color: Colors.black,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.navigate_next_sharp, color: greyColor, size: 20),
                    ],
                  ),
                ),
              ),
            ),
            initialList: widget.paiements,
            filter: (p0) {
              return widget.paiements
                  .where((c) =>
              (c.datePaiement.toString().contains(p0) ||
                  c.numero.toString().contains(p0) ||
                  c.montant.toString().contains(p0) ||
                  c.service.toString().contains(p0)))
                  .toList();
            },
            inputDecoration: InputDecoration(
              labelText: "Recherchez...",
              fillColor: Colors.white,
              focusedBorder: OutlineInputBorder(
                borderSide: const BorderSide(
                  color: kPrimaryColor,
                  width: 1.0,
                ),
                borderRadius: BorderRadius.circular(10.0),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: (user.token != null && user.token != "")
          ? Padding(
        padding: const EdgeInsets.all(8.0),
        child: Text(
          "Total restant: ${formaterMontant(widget.total)}",
          textAlign: TextAlign.center,
          style: green18MediumTextStyle,
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
