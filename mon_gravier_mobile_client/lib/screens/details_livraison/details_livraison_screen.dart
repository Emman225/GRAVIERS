import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/details_livraison.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';

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

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}details-livraison/${livraison.id}'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retLiv = DetailsLivraison.fromJson(datas);
          if (retLiv.code == 200) {
            setState(() {
              ligneCommande = retLiv.data?.ligneCommande ?? LigneCommande();
              ligneLivraison = retLiv.data?.ligneLivraison ?? LigneLivraison();
            });
          } else {
            EasyLoading.showError(retLiv.message ?? '');
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

  @override
  void initState() {
    var data = Get.arguments;
    livraison = data[0];
    niveau = data[1];
    qteLivree = data[2];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsLivraison();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails livraison",
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
      body: ListView(children: [
        SizedBox(
          width: 50,
          child: AspectRatio(
            aspectRatio: 0.88,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF5F6F9),
                borderRadius: BorderRadius.circular(15),
              ),
              child: Image.network(ligneCommande.image ??
                  'https://img.freepik.com/vecteurs-premium/fond-conception-banniere-large-3d-abstrait-bleu-diamant-brillant_181182-21825.jpg'),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(15.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                ligneCommande.nom.toString(),
                style: const TextStyle(color: Colors.black, fontSize: 16),
                maxLines: 2,
              ),
              const SizedBox(height: 8),
              Text.rich(
                TextSpan(
                  text:
                      "${formaterMontant(ligneCommande.prix?.toDouble() ?? 0)} / ${ligneCommande.unite}",
                  style: const TextStyle(
                      fontWeight: FontWeight.w600, color: kPrimaryColor),
                ),
              ),
              const SizedBox(height: 8),
              Text(ligneCommande.description.toString(),
                  style: black14RegularTextStyle),
              const SizedBox(height: 20),
              Text(
                  (ligneCommande.etatLivraison == LIVRAISON_LIVREE || ligneCommande.etatLocation == LOCATION_TERMINE)
                      ? "Qte livrée: $qteLivree"
                      : "Qte à livrer: $qteLivree",
                  style: green18MediumTextStyle),
              // Text(
              //     "Qte total: ${ligneCommande.qte} ${ligneCommande.unite}",
              //     style: red18MediumTextStyle,),
            ],
          ),
        ),
      ]),
    );
  }
}
