import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/choix_adresse/choix_adresse_screen.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/screens/resume_commande/resume_commande_screen.dart';

import '../../../globale.dart';
import '../../../models/ConfigModel.dart';
import '../../../models/adresse_de_livraison.dart';

class CheckoutCard extends StatefulWidget {
  CheckoutCard({super.key, required this.niveau, required this.data});

  int niveau = 1;
  var data = [];

  @override
  State<CheckoutCard> createState() => _CheckoutCardState();
}

class _CheckoutCardState extends State<CheckoutCard> {
  Timer? timer;
  double total = 0;
  double coutLivraison = 0;
  var lignesLivraisons = [];

  @override
  void initState() {
    if (kDebugMode) {
      print(widget.data);
    }
    total = getTotalAmount();
    super.initState();
    timer = Timer.periodic(const Duration(seconds: 1), (Timer t) {
      setState(() {
        total = getTotalAmount();
      });
    });
  }

  @override
  void dispose() {
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
            Expanded(
              child: Text.rich(
                TextSpan(
                  text: "Tot.: ${formaterMontant(total - coutReduction)}",
                  children: [
                    if (coutReduction > 0) ...[
                      TextSpan(
                        text: "\n${formaterMontant(total)}",
                        style: const TextStyle(
                          fontSize: 16,
                          color: Colors.red,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ]
                  ],
                ),
              ),
            ),
            Expanded(
              child: ElevatedButton(
                onPressed: () async {
                  switch (widget.niveau) {
                    case 1:
                      if (paniers.isNotEmpty) {
                        Get.toNamed(ChoixAdresseScreen.routeName);
                      } else {
                        EasyLoading.showError("Votre panier est vide");
                      }
                      break;
                    case 2:

                      int mode = widget.data[7];
                      bool validOk = false;

                      if (mode <= 0) {
                        EasyLoading.showError("Veuillez choisir le mode de paiement");
                        return;
                      }else{
                        if(mode == 1){
                          validOk = _validationPaiementEnLigne();
                        }if(mode == 2){
                          validOk = _validationPaiementVirement();
                        }if(mode == 3){
                          validOk = _validationPaiementAgence();
                        }
                      }

                      if(validOk){
                        if (await verifierConnexion()) {
                          try {
                            afficherChargement();

                            if (meFaireLivre) {
                              List<Map<String, dynamic>> lignes = [];
                              for (var p in paniers) {
                                if (kDebugMode) {
                                  print(p.product.toJson());
                                }
                                lignes.add({
                                  'qte': p.numOfItem,
                                  'unite_id': p.product.unite_produit_id,
                                });
                              }
                              var param = {
                                'access': user.token.toString(),
                                'type': user.type.toString(),
                                'total': getTotalAmount(),
                                "lignes": lignes,
                                "remise": coutReduction,
                                "montantTva": montantTva,
                                'long': position?.longitude ?? 0,
                                'lat': position?.latitude ?? 0,
                              };

                              if (kDebugMode) {
                                print(param);
                              }

                              retourHttp = await http
                                  .post(Uri.parse('${lienAPI()}resume-commande'),
                                  headers: {
                                    "Content-Type": "application/json"
                                  },
                                  body: jsonEncode(param))
                                  .timeout(const Duration(minutes: 2));

                              var datas = jsonDecode(retourHttp.body);

                              if (kDebugMode) {
                                print(datas);
                              }

                              if (retourHttp.statusCode == 200) {
                                if (datas['code'] == 200) {
                                  coutLivraison = double.parse(datas['data']['montant'].toString());
                                  lignesLivraisons = datas['data']['lignes'];
                                } else {
                                  EasyLoading.showError(datas['message']);
                                }
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
                        Get.toNamed(ResumeCommandeScreen.routeName, arguments: [
                          widget.data,
                          total,
                          coutLivraison,
                          lignesLivraisons,
                        ]);
                      }else{
                        EasyLoading.showError(msgErr);
                      }
                      break;
                    default:
                  }
                },
                // child: Text(widget.niveau == 1
                //     ? "Suivant"
                //     : (widget.niveau == 2 && user.clientATerme == true)
                //         ? "Valider commande "
                //         : "Paiement"),
                child: Text(widget.niveau == 1 ? "Suivant" : "Resumé"),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // _adresse,
  // _moyenPaiement,
  // noteController.text.trim(),
  // _date,
  // _typeLivraison,
  // numBlController.text.trim(),
  // _bl,
  // _modePaiement,
  // banqueController.text.trim(),
  // numCompteController.text.trim(),
  // refController.text.trim(),
  // _dateOp,
  // _vir,

  _validationPaiementEnLigne(){
    UneAdresse _adresse = widget.data[0];
    ModePaiements _moyenPaiement = widget.data[1];
    TypeLivraisons _typeLivraison = widget.data[4];
    if(meFaireLivre){
      if(_adresse.id == null || _adresse.id! <= 0){
        msgErr = "Veuillez sélectionner une adresse";
        return false;
      }
      if(widget.data[3] == null || widget.data[3] == ''){
        msgErr = "Veuillez sélectionner la date de livraison";
        return false;
      }
    }
    if(_moyenPaiement.id == null || _moyenPaiement.id! <= 0){
      msgErr = "Veuillez sélectionner le moyen de paiement";
      return false;
    }
    if(_typeLivraison.id == null || _typeLivraison.id! <= 0){
      msgErr = "Veuillez sélectionner le type de livraison";
      return false;
    }
    if(user.code_parrain == ENTREPRISE){
      if(widget.data[5] == null){
        msgErr = "Veuillez sélectionner le N° du BC";
        return false;
      }if(widget.data[6] == null){
        msgErr = "Veuillez charger le BC";
        return false;
      }
    }
    return true;
  }

  _validationPaiementVirement(){
    UneAdresse _adresse = widget.data[0];
    TypeLivraisons _typeLivraison = widget.data[4];
    if(meFaireLivre){
      if(_adresse.id == null || _adresse.id! <= 0){
        msgErr = "Veuillez sélectionner une adresse";
        return false;
      }
      if(widget.data[3] == null || widget.data[3] == ''){
        msgErr = "Veuillez sélectionner la date de livraison";
        return false;
      }
    }
    if(_typeLivraison.id == null || _typeLivraison.id! <= 0){
      msgErr = "Veuillez sélectionner le type de livraison";
      return false;
    }

    if(widget.data[8] == null || widget.data[8] == ''){
      msgErr = "Veuillez saisir la banque";
      return false;
    }if(widget.data[9] == null || widget.data[9] == ''){
      msgErr = "Veuillez saisir le numéro de compte";
      return false;
    }if(widget.data[10] == null || widget.data[10] == ''){
      msgErr = "Veuillez saisir la référence de l'opération";
      return false;
    }if(widget.data[11] == null || widget.data[11] == ''){
      msgErr = "Veuillez sélectionner la date de l'opération";
      return false;
    }if(widget.data[12] == null || widget.data[12] == ''){
      msgErr = "Veuillez sélectionner la preuve/reçu de l'opération";
      return false;
    }
    if(user.code_parrain == ENTREPRISE){
      if(widget.data[5] == null){
        msgErr = "Veuillez sélectionner le N° du BC";
        return false;
      }if(widget.data[6] == null){
        msgErr = "Veuillez charger le BC";
        return false;
      }
    }
    return true;
  }

  _validationPaiementAgence(){
    UneAdresse _adresse = widget.data[0];
    // ModePaiements _moyenPaiement = widget.data[1];
    TypeLivraisons _typeLivraison = widget.data[4];
    if(meFaireLivre){
      if(_adresse.id == null || _adresse.id! <= 0){
        msgErr = "Veuillez sélectionner une adresse";
        return false;
      }
      if(widget.data[3] == null || widget.data[3] == ''){
        msgErr = "Veuillez sélectionner la date de livraison";
        return false;
      }
    }
    // if(_moyenPaiement.id == null || _moyenPaiement.id! <= 0){
    //   msgErr = "Veuillez sélectionner le moyen de paiement";
    //   return false;
    // }
    if(_typeLivraison.id == null || _typeLivraison.id! <= 0){
      msgErr = "Veuillez sélectionner le type de livraison";
      return false;
    }
    if(user.code_parrain == ENTREPRISE){
      if(widget.data[5] == null){
        msgErr = "Veuillez sélectionner le N° du BC";
        return false;
      }if(widget.data[6] == null){
        msgErr = "Veuillez charger le BC";
        return false;
      }
    }
    return true;
  }

}
