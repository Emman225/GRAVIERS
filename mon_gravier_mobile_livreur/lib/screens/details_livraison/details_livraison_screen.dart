import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/helper/constants.dart';
import 'package:mon_gravier_com_livreur/models/RetourVehicule.dart';
import 'package:mon_gravier_com_livreur/models/details_livraison.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_effectuee_screen.dart';

import '../../constants.dart';

class DetailsLivraisonScreen extends StatefulWidget {
  static String routeName = "/details_livraison";

  const DetailsLivraisonScreen({super.key});

  @override
  State<DetailsLivraisonScreen> createState() => _DetailsLivraisonScreenState();
}

class _DetailsLivraisonScreenState extends State<DetailsLivraisonScreen> {
  UneLivraison livraison = UneLivraison();
  int niveau = 0;
  double qteLivree = 0;

  DetailsLivraison retLiv = DetailsLivraison();
  LigneCommande ligneCommande = LigneCommande();
  LigneLivraison ligneLivraison = LigneLivraison();

  chargerDetailsLivraison() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      // try/catch RÉACTIVÉ : sans lui, une erreur API (corps non-JSON, 500…) faisait
      // échouer jsonDecode sans capture -> fermerChargement() jamais atteint ->
      // "Patientez" infini. On capture, on affiche l'erreur et on ferme le loader.
      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}details-livraison/${livraison.id}'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          retLiv = DetailsLivraison.fromJson(datas);
          if (retLiv.code == 200) {
            setState(() {
              ligneCommande = retLiv.data?.ligneCommande ?? LigneCommande();
              ligneLivraison = retLiv.data?.ligneLivraison ?? LigneLivraison();
            });
          } else {
            EasyLoading.showError(retLiv.message ?? '');
          }
        } else {
          EasyLoading.showError(
              "Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer plus tard.");
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez réessayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    var data = Get.arguments;
    livraison = data[0];
    niveau = data[1];
    qteLivree = data[2];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsLivraison();
      determinePosition();
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails livraison et décision livraison",
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
      bottomNavigationBar: livraison.etatLivraison == LIVRAISON_LIVREE ? null : Padding(
        padding: const EdgeInsets.all(15.0),
        child: Row(
          children: [
            Expanded(
              child: ElevatedButton(
                  style: ButtonStyle(
                      backgroundColor:
                          MaterialStateProperty.all(Colors.blueAccent)),
                  onPressed: () {
                    final debut =
                        '${position?.latitude},${position?.longitude}';
                    final fin = '${livraison.latitude},${livraison.longitude}';
                    final String url = "https://www.google.com/maps/dir/?api=1&origin=$debut&destination=$fin";
                    if (kDebugMode) {
                      print(url);
                    }
                    lancerIfram(context,url);
                  },
                  child: const Text("Itinéraire")),
            ),
            addHorizontalSpace(10),
            if (livraison.etatLivraison == LIVRAISON_EN_ATTENTE) ...[
              Expanded(
                child: ElevatedButton(
                    style: ButtonStyle(
                        backgroundColor:
                        MaterialStateProperty.all(Colors.blueAccent)),
                    onPressed: () => _accordLivraison(),
                    child: const Text("Accepter livraison")),
              ),
            ],
            if (livraison.etatLivraison == LIVRAISON_EN_TRAITEMENT ||
                livraison.etatLivraison == LIVRAISON_EN_COURS) ...[
              Expanded(
                child: ElevatedButton(
                    style: ButtonStyle(
                        backgroundColor:
                        MaterialStateProperty.all(Colors.blueAccent)),
                    onPressed: () => Get.toNamed(LivraisonEffectueeScreen.routeName, arguments: livraison),
                    child: const Text("Finaliser la livraison")),
              ),
            ],
          ],
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      floatingActionButton: livraison.etatLivraison == LIVRAISON_EN_ATTENTE ? FloatingActionButton.extended(
        backgroundColor: redColor,
        foregroundColor: Colors.black,
        onPressed: () => _refusLivraison(),
        icon: const Icon(Icons.close, color: whiteColor),
        label: const Text('Refuser livraison', style: white12MediumTextStyle),
      )  : null,
      body: ListView(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 50),
          decoration: BoxDecoration(
            color: const Color(0xFFF5F6F9),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Image.network(ligneCommande.image ??
              'https://img.freepik.com/vecteurs-premium/fond-conception-banniere-large-3d-abstrait-bleu-diamant-brillant_181182-21825.jpg'),
        ),
        Padding(
          padding: const EdgeInsets.all(15.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if(livraison.accepte == 1 && ('${livraison.code_enlevement ?? ''}').trim().isNotEmpty) ...[
                Text('N°BE: ${livraison.code_enlevement}',
                  style: const TextStyle(
                    color: Colors.red,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
              ],
              Text(
                "Article: ${ligneCommande.nom}",
                style: const TextStyle(color: Colors.black, fontSize: 16),
                maxLines: 2,
              ),
              const SizedBox(height: 8),
              Text.rich(
                TextSpan(
                  text: livraison.etatLivraison == LIVRAISON_LIVREE
                      ? "Qte livrer: $qteLivree ${ligneCommande.unite}"
                      : "Qte à livrer: $qteLivree ${ligneCommande.unite}",
                  style: const TextStyle(
                      fontWeight: FontWeight.w600, color: kPrimaryColor),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                "Date livraison: ${formaterDate(livraison.dateLivraison ?? 'dd/MM/yyyy')}",
                style: const TextStyle(color: Colors.redAccent, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Text(
                "Cout livraison: ${formaterMontant(livraison.coutLivraison?.toDouble() ?? 0)}",
                style: const TextStyle(
                  color: Colors.blue,
                  fontWeight: FontWeight.bold,
                ),
              ),
              if (livraison.accepte == 1) ...[
                const SizedBox(height: 8),
                Text(
                  'Client: ${livraison.nomClient} - ${livraison.contactClient}',
                  style: const TextStyle(
                    color: Colors.green,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                if (('${livraison.nom_fournisseur ?? ''}').trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    'Fournisseur: ${livraison.nom_fournisseur}  \nTel Four. : ${livraison.tel_fournisseur}',
                    style: black16BoldTextStyle,
                  ),
                ],
              ],
              if (('${livraison.adresse_fournisseur ?? ''}').trim().isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  'Adresse Four. : ${livraison.adresse_fournisseur}',
                  style: const TextStyle(
                    color: Colors.black,
                  ),
                ),
              ],
              Text(
                'Adresse de livraison: ${livraison.adresse}',
                style: const TextStyle(
                  color: Colors.black,
                ),
              ),
              const SizedBox(height: 70),
            ],
          ),
        ),
      ]),
    );
  }

  _refusLivraison() async {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text("Refuser la livraison"),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 200,
              child: Column(
                children: [
                  const SizedBox(height: 10),
                  Text(
                    "Attention !!! \nVous êtes sur le point de refuser la livraison \nVoulez-vous continuer ?",
                    style: red14MediumTextStyle,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                      refuserLivraison();
                    },
                    child: const Text("Refuser la livraison"),
                  ),
                ],
              ),
            ),
          );
        });
  }

  _accordLivraison() async {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text("Accepter la livraison"),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 250,
              child: Column(
                children: [
                  const SizedBox(height: 10),
                  Text(
                    "Attention !!! \nVous êtes sur le point d'accepter la livraison \nVoulez-vous continuer ?",
                    style: red14MediumTextStyle,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                      effectuerLivraison();
                    },
                    child: const Text("Accepter la livraison"),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                    },
                    child: const Text("Fermer le fenêtre"),
                  ),
                ],
              ),
            ),
          );
        });
  }

  effectuerLivraison() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "idLivraison": livraison.id,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}accepter-livraison'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              livraison = UneLivraison.fromJson(datas['data']);
            });
            EasyLoading.showSuccess(datas['message']);
          } else {
            EasyLoading.showError(datas['message']);
          }
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  refuserLivraison() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "idLivraison": livraison.id,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}refuser-livraison'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              livraison = UneLivraison.fromJson(datas['data']);
            });
            EasyLoading.showSuccess(datas['message']);
          } else {
            EasyLoading.showError(datas['message']);
          }
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
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
