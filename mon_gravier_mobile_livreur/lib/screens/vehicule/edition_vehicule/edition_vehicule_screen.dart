import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/RetourVehicule.dart';
import 'package:select_searchable_list/select_searchable_list.dart';
import 'package:http/http.dart' as http;

import '../../../globale.dart';
import '../../../helper/constants.dart';

class EditionVehiculeScreen extends StatefulWidget {
  static String routeName = "/editionVehicule";

  const EditionVehiculeScreen({super.key});

  @override
  State<EditionVehiculeScreen> createState() => _EditionVehiculeScreenState();
}

class _EditionVehiculeScreenState extends State<EditionVehiculeScreen> {
  TextEditingController typeVehiculeController = TextEditingController();
  TextEditingController immatriculationController = TextEditingController();
  TextEditingController nomController = TextEditingController();
  TextEditingController descriptionController = TextEditingController();
  TextEditingController capaciteController = TextEditingController();
  TextEditingController marqueController = TextEditingController();
  TextEditingController modeleController = TextEditingController();
  TextEditingController disponibiliteController = TextEditingController();
  Vehicules vehicule = Vehicules();
  List<TypeVehicule> typeVehicules = [];
  int disponible = 1;

  int typeVehiculeId = 1;

  @override
  void initState() {
    typeVehiculeController = TextEditingController();
    immatriculationController = TextEditingController();
    nomController = TextEditingController();
    descriptionController = TextEditingController();
    capaciteController = TextEditingController();
    marqueController = TextEditingController();
    modeleController = TextEditingController();
    disponibiliteController = TextEditingController();
    var data = Get.arguments;
    vehicule = data[0];
    typeVehicules = data[1];
    super.initState();
  }

  @override
  void dispose() {
    typeVehiculeController.dispose();
    immatriculationController.dispose();
    nomController.dispose();
    descriptionController.dispose();
    capaciteController.dispose();
    marqueController.dispose();
    modeleController.dispose();
    disponibiliteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Edition vehicule",
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
      body: ListView(
        physics: const BouncingScrollPhysics(),
        children: [
          addVerticalSpace(20),
          Image.asset('assets/images/adresse.jpg'),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: typeVehiculeController,
              title: 'Type vehicule',
              hint: 'Choisir le type de vehicule',
              options: {
                for (var p in typeVehicules) p.id ?? 0: p.libelle.toString()
              },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  typeVehiculeId = selectedIds?.first ?? 1;
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              controller: immatriculationController,
              textInputAction: TextInputAction.next,
              maxLength: 15,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(
                labelText: "Immatriculation",
                hintText: "Immatriculation du vehicule",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              controller: nomController,
              textInputAction: TextInputAction.next,
              maxLength: 100,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(
                labelText: "Nom",
                hintText: "Nom du vehicule",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              maxLines: 3,
              controller: descriptionController,
              textInputAction: TextInputAction.done,
              decoration: const InputDecoration(
                labelText: "Description",
                hintText: "Description vehicule",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.number,
              controller: capaciteController,
              textInputAction: TextInputAction.next,
              maxLength: 5,
              decoration: const InputDecoration(
                labelText: "Capacité",
                hintText: "Capacité du vehicule en tonne",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              controller: marqueController,
              textInputAction: TextInputAction.next,
              maxLength: 30,
              decoration: const InputDecoration(
                labelText: "Marque",
                hintText: "Marque du vehicule",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              controller: modeleController,
              textInputAction: TextInputAction.done,
              maxLength: 70,
              decoration: const InputDecoration(
                labelText: "Modèle",
                hintText: "Modèle du vehicule",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: disponibiliteController,
              title: 'Disponibilite',
              hint: 'Choisir la disponibilité du vehicule',
              options: const {
                1: "DISPONIBLE",
                0: "INDISPONIBLE",
              },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  disponible = selectedIds?.first ?? 1;
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: ElevatedButton(
              onPressed: () async {
                if (_validationSaisie()) {
                  _enregistrerVehicule();
                } else {
                  EasyLoading.showError(msgErr);
                }
              },
              child: const Text("Enregistrer"),
            ),
          ),
        ],
      ),
    );
  }

  _enregistrerVehicule() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "id": vehicule.id ?? 0,
        "typeVehicule": typeVehiculeId,
        "immatriculation": immatriculationController.text.trim(),
        "nom": nomController.text.trim(),
        "description": descriptionController.text.trim(),
        "capacite": capaciteController.text.trim(),
        "marque": marqueController.text.trim(),
        "modele": modeleController.text.trim(),
        "disponible": disponible == 1 ? true : false,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}enregistrer-vehicule'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              typeVehiculeController.text = '';
              immatriculationController.text = '';
              nomController.text = '';
              descriptionController.text = '';
              capaciteController.text = '';
              marqueController.text = '';
              modeleController.text = '';
              disponibiliteController.text = '';
            });
            EasyLoading.showSuccess(datas['message']);
          }else{
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
    if (typeVehiculeId <= 0) {
      pass = false;
      msgErr = "Veuillez choisir un type de vehicule valide";
    }
    if (immatriculationController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une immatriculation valide";
    }
    if (nomController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une nom valide";
    }
    if (capaciteController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une capacité valide";
    }
    if (marqueController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une marque valide";
    }
    if (modeleController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner un modèle valide";
    }
    return pass;
  }
}
