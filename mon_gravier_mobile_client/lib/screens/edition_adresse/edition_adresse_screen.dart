import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:location_picker_flutter_map/location_picker_flutter_map.dart';
import 'package:mon_gravier_com/globale.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:mon_gravier_com/screens/afficher_carte/afficher_carte.dart';
import 'package:mon_gravier_com/screens/edition_adresse/EditionAdresseCtrl.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../components/custom_surfix_icon.dart';
import '../../models/ConfigModel.dart';

class EditionAdresseScreen extends StatefulWidget {
  static String routeName = "/editionAdresse";

  const EditionAdresseScreen({super.key});

  @override
  State<EditionAdresseScreen> createState() => _EditionAdresseScreenState();
}

class _EditionAdresseScreenState extends State<EditionAdresseScreen> {
  TextEditingController paysController = TextEditingController();
  TextEditingController villeController = TextEditingController();
  TextEditingController affichageController = TextEditingController();
  TextEditingController adresseController = TextEditingController();
  UneAdresse adresse = UneAdresse();
  List<Pays> pays = [];
  List<Ville> villesTot = [];
  List<Ville> villes = [];

  int pays_id = 1,ville_id = 1;

  PickedData? pickedData;
  String longitude = '';
  String latitude = '';

  @override
  void initState() {
    paysController = TextEditingController();
    villeController = TextEditingController();
    affichageController = TextEditingController();
    adresseController = TextEditingController();
    adresse = Get.arguments;
    pays = user.configs?.pays ?? [];
    villesTot = user.configs?.villes ?? [];
    super.initState();
  }

  @override
  void dispose() {
    paysController.dispose();
    villeController.dispose();
    affichageController.dispose();
    adresseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Edition adresse de livraison",
          style: TextStyle(color: Colors.black),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Get.back(result: UneAdresse());
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
              textEditingController: paysController,
              title: 'Pays',
              hint: 'Choisir votre pays',
              options: { for (var p in pays) p.id ?? 0 : p.nom.toString() },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  pays_id = selectedIds?.first ?? 1;
                  villes = villesTot.where((v) => v.paysId == pays_id).toList();
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: villeController,
              title: 'Ville',
              hint: 'Choisir votre ville',
              options: { for (var p in villes) p.id ?? 0 : p.nom.toString() },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  ville_id = selectedIds?.first ?? 1;
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              controller: affichageController,
              textInputAction: TextInputAction.next,
              maxLength: 50,
              decoration: const InputDecoration(
                labelText: "A Afficher",
                hintText: "Saisir l'adresse à afficher",
                // If  you are using latest version of flutter then lable text and hint text shown like this
                // if you r using flutter less then 1.20.* then maybe this is not working properly
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Location point.svg"),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              maxLines: 3,
              onTap: ()=>procDeterminerPosition(),
              controller: adresseController,
              readOnly: true,
              textInputAction: TextInputAction.done,
              decoration: const InputDecoration(
                labelText: "Adresse",
                hintText: "Cliquez pour afficher la carte",
                // If  you are using latest version of flutter then lable text and hint text shown like this
                // if you r using flutter less then 1.20.* then maybe this is not working properly
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Location point.svg"),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: ElevatedButton(
              onPressed: () async {
                if (_validationSaisie()) {
                  adresse.paysId = pays_id;
                  adresse.villeId = ville_id;
                  adresse.complementAdresse = adresseController.text.trim();
                  adresse.affichage = affichageController.text.trim();
                  adresse.longitude = longitude;
                  adresse.latitude = latitude;
                  UneAdresse adr = await editerAdresse(adresse);
                  if (adr.id! > 0) {
                    setState(() {
                      paysController.text = '';
                      villeController.text = '';
                      adresseController.text = '';
                      affichageController.text = '';
                      pays_id = 0;
                      ville_id = 0;
                    });
                    Get.back(result: adr);
                    EasyLoading.showSuccess("Adresse enregistrée avec succès");
                  }
                }else{
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

  procDeterminerPosition() async {
    var retour = await Get.toNamed(AfficherCarteScreen.routeName);
    if (retour != null) {
      pickedData = retour;
      setState(() {
        longitude = pickedData!.latLong.longitude.toString() ?? '';
        latitude = pickedData!.latLong.latitude.toString() ?? '';
        adresseController.text = pickedData!.address.toString() ?? '';
      });
    }
  }

  _validationSaisie() {
    bool pass = true;
    if (pays_id <= 0) {
      pass = false;
      msgErr = "Veuillez choisir un pays valide";
    }
    if (ville_id <= 0) {
      pass = false;
      msgErr = "Veuillez choisir une ville valide";
    }
    if (adresseController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une adresse de livraison valide";
    }
    if (affichageController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une adresse de livraison valide";
    }else{
      if (affichageController.text.trim().length > 50) {
        pass = false;
        msgErr = "La taille de l'adresse a affiché doit être au maximum 50 caractères";
      }
    }
    return pass;
  }

}
