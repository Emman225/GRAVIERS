import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/choix_adresse/choix_adresse_screen.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/screens/resume_commande/resume_commande_screen.dart';

import '../../../constants.dart';
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
    // Le total est relu chaque seconde : c'est le mécanisme d'origine, et le
    // remplacer supposerait de toucher à la gestion d'état du panier. Mais il
    // appelait `setState` à CHAQUE battement, que le total ait changé ou non —
    // la barre de commande se reconstruisait donc soixante fois par minute, en
    // permanence. On ne redessine plus que sur un vrai changement.
    timer = Timer.periodic(const Duration(seconds: 1), (Timer t) {
      if (!mounted) {
        t.cancel();
        return;
      }
      final nouveau = getTotalAmount();
      if (nouveau == total) return;
      setState(() {
        total = nouveau;
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
      decoration: const BoxDecoration(
        color: kSurfaceColor,
        border: Border(top: BorderSide(color: kBorderColor)),
      ),
      child: SafeArea(
        top: false,
        minimum:
            const EdgeInsets.fromLTRB(kSpaceXl, kSpaceMd, kSpaceXl, kSpaceMd),
        child: Row(
          children: [
            // « Tot.: 28 813 F » tenait sur une seule ligne, dans la taille du
            // corps de texte : le montant que le client s'apprête à engager
            // avait exactement le même poids visuel que le mot qui le
            // précédait.
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text("Total à payer", style: kLegendeStyle),
                  const SizedBox(height: 2),
                  Text(
                    formaterMontant(total),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: kMontantFortStyle.copyWith(fontSize: 20),
                  ),
                  // getTotalAmount() déduit DÉJÀ la remise : l'ancien
                  // « total - coutReduction » la retirait une seconde fois et
                  // affichait un total inférieur à celui réellement facturé.
                  // Le prix barré montre le montant AVANT remise.
                  if (coutReduction > 0)
                    Text(
                      formaterMontant(total + coutReduction),
                      style: const TextStyle(
                        fontSize: 12,
                        color: kTextMutedColor,
                        decoration: TextDecoration.lineThrough,
                        decorationColor: kTextMutedColor,
                        fontFeatures: [FontFeature.tabularFigures()],
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(width: kSpaceLg),
            SizedBox(
              width: 150,
              child: ElevatedButton(
                onPressed: () async {
                  switch (widget.niveau) {
                    case 1:
                      if (paniers.isNotEmpty) {
                        Get.toNamed(ChoixAdresseScreen.routeName);
                      } else {
                        afficherErreur("Votre panier est vide");
                      }
                      break;
                    case 2:

                      int mode = widget.data[7];
                      bool validOk = false;

                      if (mode <= 0) {
                        afficherErreur("Veuillez choisir le mode de paiement");
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
                        // Sans livraison, aucun calcul distant n'est requis : le coût
                        // de livraison est légitimement 0. Avec livraison, on ne pourra
                        // continuer que si l'appel resume-commande a réussi (sinon on
                        // partirait au résumé avec coutLivraison = 0 -> montant faux).
                        bool livraisonCalculee = !meFaireLivre;
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
                                  livraisonCalculee = true;
                                } else {
                                  afficherErreur(datas['message']);
                                }
                              } else {
                                // Sans cette branche, une réponse serveur en erreur ne produisait
                                // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
                                afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
                              }
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
                        // On ne va au résumé que si le coût de livraison a bien été
                        // calculé (ou n'était pas requis). Sinon le client verrait un
                        // montant erroné (livraison à 0) et pourrait valider à tort.
                        if (livraisonCalculee) {
                          Get.toNamed(ResumeCommandeScreen.routeName, arguments: [
                            widget.data,
                            total,
                            coutLivraison,
                            lignesLivraisons,
                          ]);
                        } else {
                          afficherErreur(
                              "Impossible de calculer les frais de livraison. Veuillez réessayer.");
                        }
                      }else{
                        afficherErreur(msgErr);
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
                child: Text(widget.niveau == 1 ? "Continuer" : "Résumé"),
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

  /// Bon de commande : exigé d'une ENTREPRISE, pour une VENTE comme pour une
  /// LOCATION (09/09/2026). Les deux champs sont affichés pour l'entreprise sur
  /// l'écran d'adresse (choix_adresse_screen), et le serveur exige le numéro
  /// sur les deux points d'entrée (enregistrer-commande, enregistrer-location).
  bool _validationBonDeCommande() {
    final bool entreprise = paniers.isNotEmpty && user.code_parrain == ENTREPRISE;
    if (!entreprise) return true;

    // Un champ vide passait : « null » n'était testé que sur l'absence, pas
    // sur le vide (09/09/2026). Le serveur refuse aussi, celui-ci fait foi.
    if (widget.data[5] == null || widget.data[5].toString().trim().isEmpty) {
      msgErr = "Veuillez saisir le N° de bon de commande interne";
      return false;
    }
    if (widget.data[6] == null) {
      msgErr = "Veuillez charger le BC";
      return false;
    }
    return true;
  }

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
    if(!_validationBonDeCommande()){
      return false;
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
    if(!_validationBonDeCommande()){
      return false;
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
    if(!_validationBonDeCommande()){
      return false;
    }
    return true;
  }

}
