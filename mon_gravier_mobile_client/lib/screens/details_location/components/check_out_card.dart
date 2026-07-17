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
import '../../../helper/constants.dart';

class CheckoutCard extends StatefulWidget {
  CheckoutCard({super.key, required this.niveau, required this.data, required this.montantTotal});

  int niveau = 1;
  int montantTotal = 0;
  var data = [];

  @override
  State<CheckoutCard> createState() => _CheckoutCardState();
}

class _CheckoutCardState extends State<CheckoutCard> {
  Timer? timer;
  double total = 0;
  int idCommande = 0;
  String? etatCommande;
  bool clientATerme = false;
  TextEditingController motifController = TextEditingController();

  @override
  void initState() {
    motifController = TextEditingController();
    super.initState();
    if (widget.niveau == 3) {
      setState(() {
        total = widget.montantTotal.toDouble();
        idCommande = widget.data[0];
        etatCommande = widget.data[1];
        clientATerme = widget.data[2];
      });
    }else{
      total = getTotalAmount();
      timer = Timer.periodic(const Duration(seconds: 1), (Timer t) {
        setState(() {
          total = getTotalAmount();
        });
      });
    }

    if (kDebugMode) {
      print(widget.niveau);
      print(widget.montantTotal);
      print(etatCommande);
    }
  }

  @override
  void dispose() {
    motifController.dispose();
    timer?.cancel();
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
        child: Row(
          children: [
            if (widget.niveau != 3) ...[
              Expanded(
                child: Text.rich(
                  TextSpan(
                    text: "Total:\n",
                    children: [
                      TextSpan(
                        text: formaterMontant(total),
                        style: const TextStyle(fontSize: 16, color: Colors.black),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            if (widget.niveau == 3 && etatCommande == COMMANDE_EN_ATTENTE && clientATerme == true) ...[
              Expanded(
                child: ElevatedButton(
                  onPressed: () async {
                    if (await confirmationAction(context, "Attention !!", "Voulez-vous annuler votre location ?")) {
                      if (await verifierConnexion()) {
                        try {
                          afficherChargement();

                          var param = {
                            'access': user.token.toString(),
                            'type' : user.type.toString(),
                          };

                          if (kDebugMode) {
                            print(param);
                          }

                          retourHttp = await http
                              // Endpoint LOCATION : cet écran appelait annuler-commande/{id},
                              // qui annulait la COMMANDE portant le même id que la location.
                              .post(Uri.parse('${lienAPI()}annuler-location/$idCommande'),
                              headers: {"Content-Type": "application/json"},
                              body: jsonEncode(param))
                              .timeout(const Duration(minutes: 2));

                          var datas = jsonDecode(retourHttp.body);

                          if (kDebugMode) {
                            print(datas);
                          }

                          if (retourHttp.statusCode == 200) {
                            if (datas['code'] == 200) {
                              Get.back();
                              EasyLoading.showSuccess(datas['message']);
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
                    }
                  },
                  child: const Text("Annuler"),
                ),
              ),
            ],
            if (widget.niveau == 3 && etatCommande == COMMANDE_EN_TRAITEMENT) ...[
              Expanded(
                child: ElevatedButton(
                  onPressed: () => _demandeAnnulationWidget(),
                  child: const Text("Annulation"),
                ),
              ),
            ],
            if (widget.niveau != 3) ...[
              Expanded(
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
                        if (kDebugMode) {
                          print(widget.data);
                        }
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
                              'adresse' : widget.data[0],
                              'mode_paiement' : widget.data[1],
                              'note' : widget.data[2],
                              'total': getTotalAmount(),
                              "lignes": lignes,
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
            ],
          ],
        ),
      ),
    );
  }

  _demandeAnnulationWidget() {
    showDialog(
        barrierDismissible: false,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            title: const Text("Motif d'annulation"),
            elevation: 5.0,
            content: SizedBox(
              height: 200,
              child: Column(
                children: [
                  TextFormField(
                    controller: motifController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.done,
                    maxLines: 6,
                    decoration: const InputDecoration(
                      labelText: "Motif",
                      hintText: "Saisir le motif ici...",
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                  onPressed: () {
                    Get.back();
                  },
                  child: const Text(
                    "Fermer",
                    style: TextStyle(fontSize: 14, color: redColor),
                  )),
              TextButton(
                  onPressed: () async {
                    if (motifController.text.trim().isNotEmpty) {
                      if (await verifierConnexion()) {
                        try {
                          afficherChargement();

                          var param = {
                            'access': user.token.toString(),
                            'type' : user.type.toString(),
                            'motif' : motifController.text.trim(),
                          };

                          if (kDebugMode) {
                            print(param);
                          }

                          retourHttp = await http
                              // Endpoint LOCATION (la demande était créée en type VENTE sinon).
                              .post(Uri.parse('${lienAPI()}demande-annuler-location/$idCommande'),
                              headers: {"Content-Type": "application/json"},
                              body: jsonEncode(param))
                              .timeout(const Duration(minutes: 2));

                          var datas = jsonDecode(retourHttp.body);

                          if (kDebugMode) {
                            print(datas);
                          }

                          if (retourHttp.statusCode == 200) {
                            if (datas['code'] == 200) {
                              Get.back();
                              EasyLoading.showSuccess(datas['message']);
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
                      Get.back();
                    }else{
                      EasyLoading.showError("Veuillez saisir le motif de l'annulation de votre location");
                    }
                  },
                  child: const Text(
                    "Envoyer",
                    style: TextStyle(fontSize: 14, color: greenColor),
                  ))
            ],
          );
        });
  }
}
