import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:location_picker_flutter_map/location_picker_flutter_map.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/models/resume_demande_livraison.dart';
import 'package:mon_gravier_com/screens/finalisation_demande_livraison/finalisation_demande_livraison_screen.dart';

import '../../../globale.dart';
import '../../commande_success/commande_success_screen.dart';

class CheckoutCard extends StatefulWidget {
  CheckoutCard({super.key, required this.niveau});

  int niveau = 0;

  @override
  State<CheckoutCard> createState() => _CheckoutCardState();
}

class _CheckoutCardState extends State<CheckoutCard> {

  List<UneAdresse> _listAdresse = [];
  AdresseDeLivraison adresses = AdresseDeLivraison();
  chargerAdresse() async {
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
            .post(Uri.parse('${lienAPI()}get-adresses'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
          // et le rafraîchissement échoue (voir devis_screen.dart).
          if (mounted) setState(() {
            adresses = AdresseDeLivraison.fromJson(datas);
            _listAdresse = adresses.data ?? [];
          });
          if (kDebugMode) {
            print(_listAdresse);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        if (kDebugMode) {
          print(e.toString());
        }
        afficherErreur(messageErreurTechnique(e));
      }
      fermerChargement();
    }else{
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  ResumeDemandeLivraison resumeRet = ResumeDemandeLivraison();

  double montant = 0, distance = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerAdresse();
    });
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
                  if (await verifierConnexion()) {

                    try {
                      afficherChargement();

                      demandeLivraison.produits = [];
                      List<Map<String, dynamic>> lignes = [];
                      for (var p in paniers) {
                        lignes.add({
                          'qte': p.numOfItem,
                          'unite_id': p.product.unite_id,
                        });
                      }

                      if (kDebugMode) {
                        print(_listAdresse.length);
                        // print(dep.toJson());
                        // print(des.toJson());
                      }
                      UneAdresse dep = _listAdresse.firstWhere((add) => add.id == demandeLivraison.adresseDepart);
                      UneAdresse des = _listAdresse.firstWhere((add) => add.id == demandeLivraison.adresseDestination);


                      distance = calculerDistanceEnKM(
                        LatLong(double.parse(dep.latitude.toString()), double.parse(dep.longitude.toString())),
                        LatLong(double.parse(des.latitude.toString()), double.parse(des.longitude.toString())),
                      );

                      var param = {
                        'access': user.token.toString(),
                        'type' : user.type.toString(),
                        'distance': distance.round(),
                        "lignes": lignes,
                        "demande": demandeLivraison.toJson()
                      };

                      if (kDebugMode) {
                        print(param);
                      }

                      retourHttp = await http
                          .post(Uri.parse('${lienAPI()}resume-demande-livraison'),
                          headers: {"Content-Type": "application/json"},
                          body: jsonEncode(param))
                          .timeout(const Duration(minutes: 2));

                      var datas = jsonDecode(retourHttp.body);

                      if (kDebugMode) {
                        print(datas);
                      }

                      if (retourHttp.statusCode == 200) {
                        if (datas['code'] == 200) {
                          // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
                          // et le rafraîchissement échoue (voir devis_screen.dart).
                          if (mounted) setState(() {
                            resumeRet = ResumeDemandeLivraison.fromJson(datas);
                            Get.toNamed(FinalisationDemandeLivraisonScreen.routeName, arguments: [resumeRet,distance.roundToDouble()]);
                          });
                        }else{
                          afficherErreur(datas['message']);
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
                  }else{
                    afficherInfo("Veuillez vérifier votre connexion internet");
                  }
                }  else{
                  afficherErreur("Votre panier est vide");
                }
                break;
              case 2:
                if (paniers.isNotEmpty) {
                  if (await verifierConnexion()) {

                    try {
                      afficherChargement();

                      demandeLivraison.produits = [];
                      List<Map<String, dynamic>> lignes = [];
                      for (var p in paniers) {
                        lignes.add({
                          'qte': p.numOfItem,
                          'produit': p.product.nom,
                          'unite': p.product.unite,
                          'unite_id': p.product.unite_id,
                        });
                      }

                      if (kDebugMode) {
                        print(_listAdresse.length);
                        // print(dep.toJson());
                        // print(des.toJson());
                      }
                      UneAdresse dep = _listAdresse.firstWhere((add) => add.id == demandeLivraison.adresseDepart);
                      UneAdresse des = _listAdresse.firstWhere((add) => add.id == demandeLivraison.adresseDestination);


                      distance = calculerDistanceEnKM(
                        LatLong(double.parse(dep.latitude.toString()), double.parse(dep.longitude.toString())),
                        LatLong(double.parse(des.latitude.toString()), double.parse(des.longitude.toString())),
                      );

                      var param = {
                        'access': user.token.toString(),
                        'type' : user.type.toString(),
                        'distance': distance.round(),
                        "lignes": lignes,
                        "libelle": "Livraison de (${dep.complementAdresse}) vers (${des.complementAdresse})",
                        "demande": demandeLivraison.toJson()
                      };

                      if (kDebugMode) {
                        print(param);
                      }

                      retourHttp = await http
                            .post(Uri.parse('${lienAPI()}enregistrer-demande-livraison'),
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
                          devisRepris = null;
                          Get.toNamed(CommandeSuccessScreen.routeName, arguments: datas['message']);
                        }else if (datas['code'] == 201) {
                          lancerUrl(datas['message']);
                        }else{
                          afficherErreur(datas['message']);
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
                  }else{
                    afficherInfo("Veuillez vérifier votre connexion internet");
                  }
                }  else{
                  afficherErreur("Votre panier est vide");
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
