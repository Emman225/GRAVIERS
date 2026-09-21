import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/screens/init_screen.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../components/empty_user_widget.dart';
import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../../models/code_promo.dart';
import '../commande_success/commande_success_screen.dart';
import 'components/fne_preview_widget.dart';
import '../../impression/fne_template.dart';
import 'package:intl/intl.dart';

class ResumeCommandeScreen extends StatefulWidget {
  static String routeName = "/resumeCommandeScreen";

  const ResumeCommandeScreen({super.key});

  @override
  State<ResumeCommandeScreen> createState() => _ResumeCommandeScreenState();
}

class _ResumeCommandeScreenState extends State<ResumeCommandeScreen> {

  var data, lignesLivraisons;
  double total = 0, coutLivraison = 0, montantHt = 0;

  /// Montant DÉFINITIF calculé par le serveur (prix du catalogue, TVA, remise,
  /// livraison depuis l'adresse choisie). Tant qu'il n'est pas reçu, on affiche
  /// le calcul local. Dès qu'il arrive, c'est LUI qui s'affiche : le client voit
  /// donc, avant de valider, exactement le montant qui lui sera prélevé.
  double? totalServeur;
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

    // Demander au serveur le montant définitif, sans rien enregistrer.
    WidgetsBinding.instance.addPostFrameCallback((_) => _verifierMontantServeur());
  }

  /// Interroge « verifier-montant » avec exactement les données qui seront
  /// envoyées à la validation. En cas d'échec (réseau, serveur), on ne bloque
  /// rien : l'écran continue d'afficher le calcul local.
  Future<void> _verifierMontantServeur() async {
    try {
      if (!await verifierConnexion()) return;

      List<Map<String, dynamic>> lignes = [];
      for (var p in paniers) {
        lignes.add({
          'produit_id': p.product.id,
          'qte': p.numOfItem,
          'prix': p.product.prixEffectif,
          'nbreJours': p.nbreJours,
        });
      }

      final param = {
        'access': user.token.toString(),
        'type': user.type.toString(),
        'lignes': lignes,
        'adresse': addr.id,
        // Exactement la même source que l'envoi réel (variable globale), pour que
        // la vérification porte sur les mêmes données que la validation.
        'meFaireLivre': meFaireLivre ? 1 : 0,
        'long': position?.longitude ?? 0,
        'lat': position?.latitude ?? 0,
        'reduction': reduction.id ?? 0,
        'pointUtilise': utiliserPoint == true ? nombrePoint : 0,
        'estLocation': paniers.isNotEmpty &&
            paniers.first.product.type_affaire == LOCATION,
      };

      final reponse = await http
          .post(Uri.parse('${lienAPI()}verifier-montant'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
          .timeout(const Duration(seconds: 30));

      if (reponse.statusCode != 200) return;

      final datas = jsonDecode(reponse.body);
      if (datas['code'] == 200 && datas['data'] != null) {
        final valeur = double.tryParse(datas['data']['total'].toString());
        if (valeur != null && mounted) {
          setState(() {
            totalServeur = valeur;
            coutLivraison =
                double.tryParse(datas['data']['livraison'].toString()) ??
                    coutLivraison;
            // TVA sur le transport (point 5), déjà comprise dans `total`.
            montantTvaTransport = double.tryParse(
                    datas['data']['montant_tva_transport']?.toString() ?? '0') ??
                0;
            // L'AIRSI du serveur (lot 97, 16/09/2026) : il porte aussi sur le
            // transport, comme la DGI ; la ligne « autres taxes » le reprend
            // pour que le récapitulatif s'additionne jusqu'au total serveur.
            final airsiServeur = double.tryParse(
                datas['data']['montant_airsi']?.toString() ?? '');
            if (airsiServeur != null) {
              montantAirsi = airsiServeur;
            }
          });
        }
      }
    } catch (e) {
      // Volontairement silencieux : l'affichage local reste valable.
      if (kDebugMode) {
        print('verifier-montant: $e');
      }
    }
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
          "Votre résumé",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: Container(
        width: double.infinity,
        height: heightOfScreen(context),
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
                        // Le numéro de bon interne en colonne Réf, « 01 - N° » (13/09/2026).
                        final String? numeroBon = data[5]?.toString();
                        // Transport TAXÉ : ligne du tableau ; NON taxé : présentation
                        // d'avant, sous les totaux (décision du client, 09/09/2026,
                        // la même que sur le site).
                        final bool transportEnLigne = montantTvaTransport > 0 || (tvaTransport == 1 && tva > 0);
                        for (int i = 0; i < paniers.length; i++) {
                           final p = paniers[i];
                           double montant = p.product.prixEffectif.toDouble() * p.numOfItem * (p.nbreJours ?? 1);
                           articles.add(FneArticle(
                              ref: referenceLigne(numeroBon, i + 1),
                              designation: p.product.nom ?? '',
                              puHt: p.product.prixEffectif.toDouble(),
                              qte: p.numOfItem.toDouble() * (p.nbreJours ?? 1),
                              unite: 'U',
                              taxes: 'TVA ($tva%)',
                              remise: 0,
                              montantHt: montant,
                           ));
                        }
                        if (coutLivraison > 0 && transportEnLigne) {
                           articles.add(FneArticle(
                              ref: '',
                              designation: 'Coût de livraison (${addr.complementAdresse ?? ""})',
                              puHt: coutLivraison,
                              qte: 1,
                              unite: 'Forfait',
                              // Taxé seulement si le paramétrage l'a décidé (point 5).
                              taxes: (montantTvaTransport > 0 || (tvaTransport == 1 && tva > 0)) ? 'TVA ($tva%)' : '0',
                              remise: 0,
                              montantHt: coutLivraison,
                           ));
                        }
                        
                        List<FneResumeFiscal> resumeFiscal = [];
                        // Résumé fiscal : UNE catégorie, dont l'assiette compte le
                        // transport lorsqu'il est taxé (09/09/2026, comme le site).
                        if (tva > 0) {
                          resumeFiscal.add(FneResumeFiscal(
                            categorie: 'TVA $tva% sur HT',
                            sousTotal: montantHt + (montantTvaTransport > 0 ? coutLivraison : 0),
                            taux: '$tva%',
                            totalTaxes: montantTva + montantTvaTransport,
                          ));
                        } else {
                          resumeFiscal.add(FneResumeFiscal(
                            categorie: 'TVA exo.lég - Pas de TVA sur HT 00,00% - D',
                            sousTotal: montantHt,
                            taux: '0%',
                            totalTaxes: 0,
                          ));
                        }
                        // Remise réellement appliquée (code promo ET points de
                        // fidélité) : total = HT + TVA - remise, cf. getTotalAmount().
                        // Calculée depuis les montants plutôt que depuis la globale
                        // coutReduction, remise à zéro dans certains parcours.
                        double remiseAffichee = montantHt + montantTva - total;
                        if (remiseAffichee < 0.5) remiseAffichee = 0;

                        // Le coût de livraison figure comme LIGNE d'article : il doit
                        // donc entrer dans le TOTAL HT, sinon l'addition des lignes ne
                        // retombe pas sur le total imprimé. Le montant à payer, lui,
                        // est inchangé (il incluait déjà la livraison).
                        return FnePreviewWidget(
                          config: snapshot.data!,
                          client: clientFne,
                          articles: articles,
                          totalHt: montantHt + (transportEnLigne ? coutLivraison : 0),
                          totalTva: montantTva,
                          tvaTransport: montantTvaTransport,
                          livraisonHorsTableau: transportEnLigne ? 0 : coutLivraison,
                          totalTtc: montantHt + montantTva + (transportEnLigne ? coutLivraison + montantTvaTransport : 0),
                          // AIRSI (10/09/2026) : la case « autres taxes ».
                          autresTaxes: montantAirsi,
                          // Montant du serveur dès qu'il est connu : c'est celui
                          // qui sera réellement prélevé.
                          totalAPayer: totalServeur ?? (total + coutLivraison + montantTvaTransport),
                          remise: remiseAffichee,
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
                        // VERT, à la demande : c'est le bouton qui engage
                        // la commande, et le vert le distingue de toutes les
                        // autres actions de l'application. C'est le seul
                        // endroit où cette couleur sert d'action.
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: greenColor,
                            foregroundColor: whiteColor,
                          ),
                          onPressed: () => _validerCommande(),
                          // Le libellé suit le MODE choisi, plus le statut du client :
                          // un client à terme qui a choisi « En ligne » lisait
                          // « Valider ma commande » avant d'être envoyé vers la
                          // passerelle. Seul le mode 1 déclenche un paiement immédiat.
                          child: Text(mode == 1 ? "Payer en ligne" : "Valider ma commande "),
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
                      labelText: "Libellé *",
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
                    "Annuler",
                    style: TextStyle(fontSize: 14, color: kTextSecondaryColor),
                  )),
              TextButton(
                  onPressed: () async {
                    Get.back();
                    _enregistrerDevis();
                  },
                  child: const Text(
                    "Enregistrer",
                    style: TextStyle(
                        fontSize: 14,
                        color: kPrimaryColor,
                        fontWeight: FontWeight.w700),
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
          // Le numéro de bon interne est figé sur le devis (09/09/2026).
          "numero_bc": data[5],
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
              afficherSucces(datas['message']);
              paniers.clear();
              devisRepris = null;
              // Le panier vidé, il ne provient plus d aucun devis : sans cette
              // remise à zéro, une commande passée PLUS TARD depuis un panier neuf
              // aurait clos un devis sans rapport avec elle.
              devisRepris = null;
              // Attendre quelques secondes (durée du message)
              await Future.delayed(const Duration(seconds: 3));
              Get.offAllNamed(InitScreen.routeName);
            } else {
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
      } else {
        afficherInfo("Veuillez vérifier votre connexion internet");
      }
    } else {
      afficherErreur(msgErr);
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
          // Devis d'origine, s'il y en a un : le serveur le clôturera.
          //
          // C'est CET écran — « Votre Resumé », avec la proforma — qui valide la
          // commande. Je n'avais ajouté le champ que dans details_commande, un
          // autre chemin : le devis restait donc ouvert, et rien ne le montrait
          // puisque la commande, elle, s'enregistrait normalement.
          'devis_id': devisRepris,
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
            devisRepris = null;
            // Le panier vidé, il ne provient plus d aucun devis : sans cette
            // remise à zéro, une commande passée PLUS TARD depuis un panier neuf
            // aurait clos un devis sans rapport avec elle.
            devisRepris = null;
            reduction = Reduction();
            Get.toNamed(CommandeSuccessScreen.routeName,
                arguments: datas['message']);
          } else if (datas['code'] == 201) {
            lancerUrl(datas['message']);
          } else {
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
        if (kDebugMode) {
          print(e.toString());
        }
      }

      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

}
