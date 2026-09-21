import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:http/http.dart' as http;

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/models/InformationsCommande.dart';
import 'package:mon_gravier_com/models/retour_details_livraison.dart';
import 'package:mon_gravier_com/models/retour_liste_demande_livraison.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison_affiche/details_demande_livraison_affiche_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/etat_vide.dart';
import '../../../components/carte_operation.dart';
import '../../../globale.dart';

class ListeDemandeLivraison extends StatelessWidget {
  ListeDemandeLivraison({super.key, required this.liste});

  List<DataListeDemandeLivraison> liste;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        width: double.infinity,
        height: heightOfScreen(context),
        child: Padding(
          padding: const EdgeInsets.all(15.0),
          child: SearchableList<DataListeDemandeLivraison>(
            searchFieldEnabled: true,
            shrinkWrap: true,
            autoFocusOnSearch: false,
            sortWidget: const Icon(Icons.sort),
            sortPredicate: (a, b) {
              int mtna = a.id ?? 0;
              int mtnb = b.id ?? 0;
              return mtna.compareTo(mtnb);
            },
            physics: const BouncingScrollPhysics(),
            builder: (liste, index, c) => Padding(
              padding: const EdgeInsets.only(bottom: kSpaceMd),
              child: CarteOperation(
                icone: Icons.local_shipping_outlined,
                numero: "Demande n° ${c.numero}",
                montant: formaterMontant(c.montantTotal?.toDouble() ?? 0),
                mention: c.modePaiement == null
                    ? null
                    : "Paiement : ${c.modePaiement}",
                date: formaterDate(c.dateLivraison.toString(),
                    format: 'd MMMM y'),
                // Ouvre le détail de la demande. Cette action était portée
                // par un GestureDetector ENVELOPPANT la carte, laquelle avait
                // reçu un `onTap: () {}` vide en attendant. Un InkWell muni
                // d'une action, même sans effet, absorbe le geste : le
                // détecteur extérieur n'était plus jamais appelé, et toucher
                // une ligne ne faisait plus rien.
                onTap: () async {
                if (await verifierConnexion()) {
                  try {
                    afficherChargement();

                    var param = {
                      'access': user.token.toString(),
                      'type': user.type.toString(),
                    };

                    if (kDebugMode) {
                      print(param);
                    }

                    retourHttp = await http
                        .post(Uri.parse('${lienAPI()}details-demande-livraison/${c.id}'),
                            headers: {"Content-Type": "application/json"},
                            body: jsonEncode(param))
                        .timeout(const Duration(minutes: 2));

                    var datas = jsonDecode(retourHttp.body);

                    if (kDebugMode) {
                      print(datas);
                    }

                    if (retourHttp.statusCode == 200) {
                      RetourDetailsLivraison retDetLiv = RetourDetailsLivraison.fromJson(datas);
                      if (retDetLiv.code == 200) {
                        List<Cart> list = [];
                        // Les codes de livraison des courses acceptées (10/09/2026),
                        // toutes lignes confondues, pour l'en-tête du détail.
                        final List<CodesLigne> codes = [];

                        List<DataRetourDetailsLivraison> details = retDetLiv.data ?? [];
                        for (var det in details) {
                          codes.addAll(det.codes);
                          list.add(
                            Cart(
                              type: 2,
                                product: Produits(
                                  nom: "${det.nomProduit} (${det.etatLivraison})",
                                  unite: det.unite,
                                  unite_id: det.uniteProduitId,
                                ),
                                numOfItem: det.qte!.toDouble()
                            )
                          );
                        }
                        Get.toNamed(DetailsDemandeLivraisonAfficheScreen.routeName, arguments: {
                          'lignes': list,
                          'codes': codes,
                          'numero': c.numero,
                        });
                      } else {
                        afficherErreur(retDetLiv.message ?? '');
                      }
                    } else {
                      // Sans cette branche, une réponse serveur en erreur ne produisait
                      // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
                      afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
                    }
                  } catch (e) {
                    user.code = 500;
                    user.message =
                        messageErreurTechnique(e);
                    if (kDebugMode) {
                      print(e.toString());
                    }
                  }

                  fermerChargement();
                } else {
                  afficherInfo(
                      "Veuillez vérifier votre connexion internet");
                }
              },
              ),
            ),
            emptyWidget: const EtatVide(
              compact: true,
              icone: Icons.local_shipping_outlined,
              titre: "Aucune demande de livraison",
              message:
                  "Les demandes de cet état apparaîtront dans cette liste.",
            ),
            initialList: liste,
            filter: (p0) {
              return liste
                  .where((c) => (c.libelle.toString().contains(p0) ||
                      c.dateLivraison.toString().contains(p0) ||
                      c.montantTotal.toString().contains(p0) ||
                      c.modePaiement.toString().contains(p0)))
                  .toList();
            },
            inputDecoration: const InputDecoration(
              hintText: "Rechercher une demande...",
              floatingLabelBehavior: FloatingLabelBehavior.never,
              prefixIcon: Icon(Icons.search, size: 20),
            ),
          ),
        ),
      ),
    );
  }
}
