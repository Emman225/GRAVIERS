import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:http/http.dart' as http;

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/models/retour_details_livraison.dart';
import 'package:mon_gravier_com/models/retour_liste_demande_livraison.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison_affiche/details_demande_livraison_affiche_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';
import '../../../models/Commande.dart';

class ListeDemandeLivraison extends StatelessWidget {
  ListeDemandeLivraison({super.key, required this.liste});

  List<DataListeDemandeLivraison> liste;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
            builder: (liste, index, c) => GestureDetector(
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

                        List<DataRetourDetailsLivraison> details = retDetLiv.data ?? [];
                        for (var det in details) {
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
                        Get.toNamed(DetailsDemandeLivraisonAfficheScreen.routeName, arguments: list);
                      } else {
                        EasyLoading.showError(retDetLiv.message ?? '');
                      }
                    }
                  } catch (e) {
                    user.code = 500;
                    user.message =
                        "Une erreur s'est produite veuillez reesayer plus tard";
                    if (kDebugMode) {
                      print(e.toString());
                    }
                  }

                  fermerChargement();
                } else {
                  EasyLoading.showInfo(
                      "Veuillez vérifier votre connexion internet");
                }
              },
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: 120,
                  decoration: BoxDecoration(
                    color: Colors.grey[200],
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    children: [
                      const SizedBox(
                        width: 10,
                      ),
                      Container(
                          width: 80,
                          height: 80,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(30),
                            image: const DecorationImage(
                                image: AssetImage("assets/images/truck.gif"),
                                fit: BoxFit.cover,
                                opacity: 0.6),
                          ),
                          child: Container()),
                      const SizedBox(
                        width: 10,
                      ),
                      Flexible(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              '# ${c.numero}',
                              style: const TextStyle(
                                color: Colors.red,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              formaterMontant(c.montantTotal?.toDouble() ?? 0),
                              style: const TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              'Paiement: ${c.modePaiement}',
                              style: const TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '${formaterDate(c.dateLivraison.toString(), format: 'd MMMM y')}',
                              style: const TextStyle(
                                color: Colors.black,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.navigate_next_sharp, color: blackColor, size: 20),
                    ],
                  ),
                ),
              ),
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
    );
  }
}
