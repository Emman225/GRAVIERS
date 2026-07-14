import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/choix_adresse/choix_adresse_screen.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/screens/commande_success/commande_success_screen.dart';

import '../../../globale.dart';

class CheckoutCard extends StatefulWidget {
  CheckoutCard({super.key, required this.niveau});

  int niveau = 0;

  @override
  State<CheckoutCard> createState() => _CheckoutCardState();
}

class _CheckoutCardState extends State<CheckoutCard> {

  @override
  void initState() {
    super.initState();
    if (kDebugMode) {
      print(widget.niveau);
    }
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        vertical: 16,
        horizontal: 20,
      ),
      // height: 174,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(30),
          topRight: Radius.circular(30),
        ),
        boxShadow: [
          BoxShadow(
            offset: const Offset(0, -15),
            blurRadius: 20,
            color: const Color(0xFFDADADA).withOpacity(0.15),
          )
        ],
      ),
      child: SafeArea(
        child: ElevatedButton(
          onPressed: () async {
            switch (widget.niveau) {
              case 1:
                if (paniers.isNotEmpty) {
                  Get.toNamed(ChoixAdresseScreen.routeName);
                }  else{
                  EasyLoading.showError("Votre panier est vide");
                }
                break;
              case 2:
                if (await verifierConnexion()) {
                  try {
                    afficherChargement();

                    List<Map<String, dynamic>> lignes = [];
                    for (var p in paniers) {
                      lignes.add({
                        'produit_id': p.product.id,
                        'qte': p.numOfItem,
                        'prix': p.product.prixEffectif,
                      });
                    }

                    var param = {
                      'access': user.token.toString(),
                      'type' : user.type.toString(),
                      // 'adresse' : widget.data[0],
                      // 'mode_paiement' : widget.data[1],
                      // 'note' : widget.data[2],
                      // 'total': getTotalAmount(),
                      // "lignes": lignes,
                    };

                    if (kDebugMode) {
                      print(param);
                    }

                    retourHttp = await http
                        .post(Uri.parse('${lienAPI()}enregistrer-commande'),
                        headers: {"Content-Type": "application/json"},
                        body: jsonEncode(param))
                        .timeout(const Duration(minutes: 2));

                    var datas = jsonDecode(retourHttp.body);

                    if (kDebugMode) {
                      print(datas);
                    }

                    if (retourHttp.statusCode == 200) {
                      if (datas['code'] == 200) {
                        paniers.clear();
                        Get.toNamed(CommandeSuccessScreen.routeName, arguments: datas['message']);
                      }else{
                        EasyLoading.showError(datas['message']);
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
                }else{
                  EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
                }
                break;
              default:
            }
          },
          child: Text(widget.niveau == 1 ? "Finaliser" : "Paiement"),
        ),
      ),
    );
  }
}
