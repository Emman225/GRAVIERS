import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import '../../../globale.dart';
import '../../../helper/constants.dart';

class LivraisonEffectueeScreen extends StatefulWidget {
  static String routeName = "/livraisonEffectuee";

  const LivraisonEffectueeScreen({super.key});

  @override
  State<LivraisonEffectueeScreen> createState() =>
      _LivraisonEffectueeScreenState();
}

class _LivraisonEffectueeScreenState extends State<LivraisonEffectueeScreen> {
  TextEditingController numLivraisonController = TextEditingController();
  TextEditingController noteController = TextEditingController();
  UneLivraison livraison = UneLivraison();
  String msgErr = "";

  @override
  void initState() {
    livraison = Get.arguments;
    numLivraisonController = TextEditingController();
    noteController = TextEditingController();
    if (kDebugMode) {
      print(livraison.numero);
    }
    super.initState();
  }

  @override
  void dispose() {
    numLivraisonController.dispose();
    noteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Livraison effectuée",
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
        child: ListView(
          physics: const BouncingScrollPhysics(),
          children: [
            addVerticalSpace(20),
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: TextFormField(
                keyboardType: TextInputType.text,
                controller: numLivraisonController,
                textInputAction: TextInputAction.done,
                decoration: const InputDecoration(
                  labelText: "Numéro de livraison du client",
                  hintText: "Saisissez le numéro de livraison ici...",
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: TextFormField(
                maxLines: 3,
                keyboardType: TextInputType.text,
                controller: noteController,
                textInputAction: TextInputAction.done,
                decoration: const InputDecoration(
                  labelText: "Note supplémentaire (optionnelle)",
                  hintText: "Saisissez un commentaire ici...",
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(15.0),
              child: ElevatedButton(
                onPressed: () async {
                  if (_validationSaisie()) {
                    _enregistrerFinLivraison();
                  } else {
                    EasyLoading.showError(msgErr);
                  }
                },
                child: const Text("Enregistrer la fin de livraison"),
              ),
            ),
            addVerticalSpace(10),
          ],
        ),
      ),
    );
  }

  _enregistrerFinLivraison() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "idLivraison": livraison.id ?? 0,
        "note": noteController.text.trim(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}enregistrer-fin-livraison'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              numLivraisonController.text = '';
              noteController.text = '';
              livraison = UneLivraison.fromJson(datas['data']);
            });
            EasyLoading.showSuccess(datas['message']);
            Get.back();
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

  _validationSaisie() {
    bool pass = true;
    if (numLivraisonController.text.trim() != livraison.numero) {
      pass = false;
      msgErr =
          "Veuillez renseigner un numéro de livraison valide. c'est le client qui dois vous le communiquer";
    }
    if (livraison.etatLivraison == LIVRAISON_LIVREE) {
      pass = false;
      msgErr = "Cette livraison est déjà validé";
    }
    if (livraison.livreurId != user.livreur?.id) {
      pass = false;
      msgErr = "Vous n'êtes pas associé à cette livraison";
    }
    return pass;
  }
}
