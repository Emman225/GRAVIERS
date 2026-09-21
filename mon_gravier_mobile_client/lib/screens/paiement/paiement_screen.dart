import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/impression/impression_recu_paiement_pdf.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../../components/carte_operation.dart';
import '../../helper/constants.dart';
import '../../models/retour_liste_un_new_paiement.dart';

class PaiementScreen extends StatefulWidget {
  static String routeName = "/paiement_listing";

  const PaiementScreen({super.key});

  @override
  State<PaiementScreen> createState() => PaiementScreenState();
}

class PaiementScreenState extends State<PaiementScreen> {
  List<UnNewPaiement> paiements = [];
  RetourUnNewPaiement pai = RetourUnNewPaiement();
  List<Widget> pagesPaiement = [];
  List<UnNewPaiement> paiementAttente = [];
  List<UnNewPaiement> paiementEffectuee = [];
  TextEditingController modePaiementController = TextEditingController();
  int leStatut = 0;
  List<int> ids = [];

  chargerPaiement() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-paiement'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          pai = RetourUnNewPaiement.fromJson(datas);
          if (pai.code == 200) {
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              paiements = pai.data ?? [];
            });
          } else {
            afficherErreur(pai.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerPaiement();
      }
    });
  }

  @override
  void dispose() {
    modePaiementController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Paiements effectués et impression de reçu"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: Padding(
                    padding: const EdgeInsets.all(8.0),
                    child: SearchableList<UnNewPaiement>(
                      searchFieldEnabled: true,
                      shrinkWrap: true,
                      autoFocusOnSearch: false,
                      sortWidget: const Icon(Icons.sort),
                      sortPredicate: (a, b) {
                        double mtna = a.id?.toDouble() ?? 0;
                        double mtnb = b.id?.toDouble() ?? 0;
                        return mtna.compareTo(mtnb);
                      },
                      physics: const BouncingScrollPhysics(),
                      builder: (paiements, index, p) => Padding(
                          padding: const EdgeInsets.only(bottom: kSpaceMd),
                          child: CarteOperation(
                            icone: Icons.payments_outlined,
                            numero: "Règlement ${p.code}",
                            montant: formaterMontant(
                                p.montantTotal?.toDouble() ?? 0),
                            // L'affaire réglée devant le libellé (10/09/2026).
                            mention: (p.affaire ?? '').isNotEmpty
                                ? "${p.affaire} — ${p.libelle}"
                                : p.libelle.toString(),
                            date: "${p.datePaiement}",
                            // L'état du circuit de preuve (point 20) : « Effectuée »
                            // en vert, « Validée — en cours » en orange, « Payé » en vert.
                            statut: p.libelleEtat,
                            couleurStatut: (p.libelleEtat ?? '').startsWith('Validée')
                                ? kWarningColor
                                : kSuccessColor,
                            fondStatut: (p.libelleEtat ?? '').startsWith('Validée')
                                ? kWarningSoftColor
                                : kSuccessSoftColor,
                            // Ouvre le reçu du règlement, comme avant la
                            // refonte de cette liste.
                            onTap: () => Get.toNamed(
                              ImpressionRecuPaiementPdf.routeName,
                              arguments: [2, "", p.id],
                            ),
                          ),
                        ),
                      emptyWidget: const EtatVide(
                        compact: true,
                        icone: Icons.payments_outlined,
                        titre: "Aucun règlement",
                        message:
                            "Vos règlements apparaîtront ici, avec leur reçu.",
                      ),
                      initialList: paiements,
                      filter: (p0) {
                        return paiements
                            .where((c) =>
                        (c.datePaiement.toString().contains(p0) ||
                            c.code.toString().contains(p0) ||
                            c.montantTotal.toString().contains(p0) ||
                            (c.affaire ?? '').contains(p0) ||
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
                ),
              ),
      ),
    );
  }

}
