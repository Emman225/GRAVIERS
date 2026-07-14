import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/screens/cart/cart_screen.dart';
import 'package:mon_gravier_com/screens/init_screen.dart';

import '../../components/empty_user_widget.dart';
import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../../models/code_promo.dart';
import '../cart/components/cart_card.dart';
import '../commande_success/commande_success_screen.dart';
import 'components/fne_preview_widget.dart';
import '../../impression/fne_template.dart';
import 'package:intl/intl.dart';
import '../../models/ConfigModel.dart';
import '../../models/code_promo.dart';
import '../cart/components/cart_card.dart';
import '../commande_success/commande_success_screen.dart';

class ResumeCommandeScreen extends StatefulWidget {
  static String routeName = "/resumeCommandeScreen";

  const ResumeCommandeScreen({super.key});

  @override
  State<ResumeCommandeScreen> createState() => _ResumeCommandeScreenState();
}

class _ResumeCommandeScreenState extends State<ResumeCommandeScreen> {

  var data, lignesLivraisons;
  double total = 0, coutLivraison = 0, montantHt = 0;
  TypeLivraisons tl = TypeLivraisons();
  UneAdresse addr = UneAdresse();
  ModePaiements mp = ModePaiements();
  int mode = 0;
  String libMode = "";
  TextEditingController libelleController = TextEditingController();


  @override
  void initState() {
    var datas = Get.arguments;
    data = datas[0];
    total = datas[1];
    coutLivraison = datas[2];
    lignesLivraisons = datas[3];
    libelleController = TextEditingController();

    tl = data[4];
    addr = data[0];
    mp = data[1];
    mode = data[7];
    if (kDebugMode) {
      print("<------------------------>");
      print(tl.toJson());
    }
    switch (mode) {
      case 1:
        libMode = "Paiement en ligne";
        break;
      case 2:
        libMode = "Paiement par virement";
        break;
      case 3:
        libMode = "Paiement en agence";
        break;
    }

    if (paniers.first.product.type_affaire == VENTE) {
      montantHt = paniers.fold(0.0,
          (sum, p) => sum + (p.product.prixEffectif.toDouble() * p.numOfItem));
    } else if (paniers.first.product.type_affaire == LOCATION) {
      montantHt = paniers.fold(
          0.0,
          (sum, p) =>
              sum +
              (p.product.prixEffectif.toDouble() *
                  p.numOfItem *
                  p.nbreJours!.toDouble()));
    }

    super.initState();
  }

  @override
  void dispose() {
    libelleController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Votre Resumé",
          style: TextStyle(color: Colors.black),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
      ),
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
            padding: const EdgeInsets.symmetric(horizontal: 5),
            child: ConstrainedBox(
                constraints: BoxConstraints(
                  minHeight: 50.0,
                  maxHeight: heightOfScreen(context) / 2,
                ),
                child: ListView(
                  physics: const BouncingScrollPhysics(),
                  children: [
                    FutureBuilder<FneConfig>(
                      future: FneTemplate.chargerConfig(),
                      builder: (context, snapshot) {
                        if (!snapshot.hasData) {
                          return const Padding(
                            padding: EdgeInsets.all(50.0),
                            child: Center(child: CircularProgressIndicator()),
                          );
                        }
                        
                        FneClient clientFne = FneClient(
                           nom: user.nom ?? '',
                        );
                        
                        List<FneArticle> articles = [];
                        for (int i = 0; i < paniers.length; i++) {
                           final p = paniers[i];
                           double montant = p.product.prixEffectif.toDouble() * p.numOfItem * (p.nbreJours ?? 1);
                           articles.add(FneArticle(
                              ref: (i + 1).toString().padLeft(2, '0'),
                              designation: p.product.nom ?? '',
                              puHt: p.product.prixEffectif.toDouble(),
                              qte: p.numOfItem.toDouble() * (p.nbreJours ?? 1),
                              unite: 'U',
                              taxes: 'TVA ($tva%)',
                              remise: 0,
                              montantHt: montant,
                           ));
                        }
                        if (coutLivraison > 0) {
                           articles.add(FneArticle(
                              ref: '',
                              designation: 'Coût de livraison (${addr.complementAdresse ?? ""})',
                              puHt: coutLivraison,
                              qte: 1,
                              unite: 'Forfait',
                              taxes: '0',
                              remise: 0,
                              montantHt: coutLivraison,
                           ));
                        }
                        
                        List<FneResumeFiscal> resumeFiscal = [];
                        if (tva > 0) {
                          resumeFiscal.add(FneResumeFiscal(
                            categorie: 'TVA $tva% sur HT',
                            sousTotal: montantHt,
                            taux: '$tva%',
                            totalTaxes: montantTva,
                          ));
                        } else {
                          resumeFiscal.add(FneResumeFiscal(
                            categorie: 'TVA exo.lég - Pas de TVA sur HT 00,00% - D',
                            sousTotal: montantHt,
                            taux: '0%',
                            totalTaxes: 0,
                          ));
                        }
                        
                        return FnePreviewWidget(
                          config: snapshot.data!,
                          client: clientFne,
                          articles: articles,
                          totalHt: montantHt,
                          totalTva: montantTva,
                          totalTtc: montantHt + montantTva,
                          autresTaxes: 0,
                          totalAPayer: total + coutLivraison,
                          resumeFiscal: resumeFiscal,
                          date: DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
                          modePaiement: libMode,
                          adresseLivraison: addr.complementAdresse,
                        );
                      }
                    ),
                    addVerticalSpace(15),

                    if(user.token == null || user.token == "") ...[
                      const EmptyUserWidget(showImage: false, msg: "Veuillez vous connecter ou vous inscrire avant de finaliser votre commande"),
                    ]else ...[
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: ElevatedButton(
                          style: const ButtonStyle(
                              backgroundColor:
                              MaterialStatePropertyAll(greenColor)),
                          onPressed: () => _validerCommande(),
                          child: Text(
                              (user.clientATerme == true || mode == 2 || mode == 3)
                                  ? "Valider ma commande "
                                  : "Payer en ligne"),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: ElevatedButton(
                          onPressed: () => _saveDevisWidget(),
                          child: const Text("Enregistrer comme devis"),
                        ),
                      ),
                      addVerticalSpace(15),
                    ]
                  ],
                ))),
      ),
      // bottomNavigationBar: CheckoutCard(niveau: 2),
    );
  }

  _saveDevisWidget(){
    showDialog(
        barrierDismissible: false,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            title: const Text("Saisir identifiant"),
            elevation: 5.0,
            content: SizedBox(
              height: 220,
              child: Column(
                children: [
                  const Text(
                    "Veuillez saisir un libellé pour identifier votre devis",
                    style: black14BoldTextStyle,
                  ),
                  addVerticalSpace(30),
                  TextFormField(
                    controller: libelleController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.done,
                    maxLength: 50,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: "Libellé",
                      hintText: "Saisir le libellé ici...",
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
                    Get.back();
                    _enregistrerDevis();
                  },
                  child: const Text(
                    "Enregistrer",
                    style: TextStyle(fontSize: 14, color: greenColor),
                  ))
            ],
          );
        });
  }

  _enregistrerDevis() async {
    if (_validationSaisie()) {
      if (await verifierConnexion()) {
        afficherChargement();

        List<Map<String, dynamic>> lignes = [];
        for (var p in paniers) {
          lignes.add({
            'produit_id': p.product.id,
            'qte': p.numOfItem,
            'prix': p.product.prixEffectif,
            'nbreJours': p.nbreJours,
            'dateDebut': p.dateDebut,
            'dateDeFin': p.dateDeFin,
          });
        }

        var param = {
          "access": user.token.toString(),
          "type": user.type.toString(),
          "libelle": libelleController.text.trim(),
          "montantHt": montantHt,
          "coutReduction": coutReduction,
          "montantTva": montantTva,
          "coutLivraison": coutLivraison,
          "meFaireLivre": meFaireLivre,
          "lignes": lignes,
          "total": total + coutLivraison,
          "modePaiement": mode,
          "moyenPaiement": mp.id,
          "typeLivraison": tl.id,
          "adresseLivraison": addr.id,
          "dateLivraison": data[3],
          "note": data[2],
          "service": paniers.first.product.type_affaire,
          "long": position?.longitude,
          "lat": position?.latitude,
        };

        if (kDebugMode) {
          print(jsonEncode(param));
        }

        try {
          retourHttp = await http
              .post(Uri.parse('${lienAPI()}enregistrer-devis'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
              .timeout(const Duration(minutes: 2));
          if (kDebugMode) {
            print(retourHttp.body);
          }
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          if (retourHttp.statusCode == 200) {
            if (datas['code'] == 200) {
              EasyLoading.showSuccess(datas['message']);
              paniers.clear();
              // Attendre quelques secondes (durée du message)
              await Future.delayed(const Duration(seconds: 3));
              Get.offAllNamed(InitScreen.routeName);
            } else {
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
      } else {
        EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
      }
    } else {
      EasyLoading.showError(msgErr);
    }
  }

  _validationSaisie() {
    bool pass = true;
    if (libelleController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir un identifiant";
    }
    return pass;
  }

  _validerCommande() async {
    if (await verifierConnexion()) {
      try {
        afficherChargement();

        List<Map<String, dynamic>> lignes = [];
        for (var p in paniers) {
          lignes.add({
            'produit_id': p.product.id,
            'qte': p.numOfItem,
            'prix': p.product.prixEffectif,
            'debut': p.dateDebut,
            'fin': p.dateDeFin,
            'nbreJours': p.nbreJours,
          });
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

        Uint8List? bcByte, virByte;

        if (data[6] != null && user.code_parrain == ENTREPRISE) {
          bcByte = await data[6]?.readAsBytes();
        }
        if (data[12] != null && mode == 2) {
          virByte = await data[12]?.readAsBytes();
        }

        var param = {
          'access': user.token.toString(),
          'type': user.type.toString(),
          'adresse': addr.id,
          'mode_paiement': mode,
          'moyen_paiement': mp.id,
          'note': data[2],
          'date_livraison': data[3],
          'type_livraison': tl.id,
          'numero_bc': data[5],
          'bc_file': bcByte == null ? null : base64Encode(bcByte),
          'total': getTotalAmount() + coutLivraison,
          "lignes": lignes,
          "reduction": reduction.id,
          "remise": coutReduction,
          "montantTva": montantTva,
          "montantLivraison": coutLivraison,
          "meFaireLivre": meFaireLivre,
          "pointUtilise": utiliserPoint == true ? nombrePoint : 0,
          'long': position?.longitude ?? 0,
          'lat': position?.latitude ?? 0,
          'banque': data[8],
          'numCompte': data[9],
          'refOperation': data[10],
          'dateOperation': data[11],
          'fichierVir': virByte == null ? null : base64Encode(virByte),
        };

        if (kDebugMode) {
          print(param);
        }

        if (paniers.first.product.type_affaire == VENTE) {
          retourHttp = await http
              .post(Uri.parse('${lienAPI()}enregistrer-commande'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
              .timeout(const Duration(minutes: 2));
        } else {
          retourHttp = await http
              .post(Uri.parse('${lienAPI()}enregistrer-location'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
              .timeout(const Duration(minutes: 2));
        }

        var datas = jsonDecode(retourHttp.body);

        if (kDebugMode) {
          print(datas);
        }

        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            paniers.clear();
            reduction = Reduction();
            Get.toNamed(CommandeSuccessScreen.routeName,
                arguments: datas['message']);
          } else if (datas['code'] == 201) {
            lancerUrl(datas['message']);
          } else {
            EasyLoading.showError(datas['message']);
          }
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        if (kDebugMode) {
          print(e.toString());
        }
      }

      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

}
