import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';
import 'package:select_searchable_list/select_searchable_list.dart';
import '../../../globale.dart';
import '../../../helper/constants.dart';
import '../../components/separateur_de_milier.dart';
import '../../models/User.dart';

class EditDemandeRetraitScreen extends StatefulWidget {
  static String routeName = "/editionDemandeRetrait";

  const EditDemandeRetraitScreen({super.key});

  @override
  State<EditDemandeRetraitScreen> createState() => _EditDemandeRetraitScreenState();
}

class _EditDemandeRetraitScreenState extends State<EditDemandeRetraitScreen> {
  TextEditingController montantController = TextEditingController();
  TextEditingController modePaiementController = TextEditingController();
  TextEditingController compteController = TextEditingController();
  String msgErr = "";
  List<ModePaiements> _listModePaiement = [];
  double _montant = 0;
  int _mode = 0;
  DemandePaiement demande = DemandePaiement();

  @override
  void initState() {
    demande = Get.arguments;
    _listModePaiement = user.configs?.modePaiements ?? [];
    montantController = TextEditingController(text: demande.montant != null ? demande.montant.toString() : '');
    modePaiementController = TextEditingController(text: demande.modePaiement);
    _mode = demande.modePaiementId ?? 0;
    _montant = demande.montant?.toDouble() ?? 0;
    compteController = TextEditingController(text: demande.numero_compte != null ? demande.numero_compte.toString() : '');
    super.initState();
  }

  @override
  void dispose() {
    montantController.dispose();
    modePaiementController.dispose();
    compteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Demander un paiement",
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
              child: DropDownTextField(
                textEditingController: modePaiementController,
                title: 'Mode de paiement',
                hint: 'Choisir un mode de paiement',
                options: {
                  for (var p in _listModePaiement)
                    p.id ?? 0: p.libelle.toString()
                },
                multiple: false,
                textInputAction: TextInputAction.next,
                onChanged: (selectedIds) {
                  setState(() {
                    _mode = selectedIds?.first ?? 0;
                  });
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: TextFormField(
                keyboardType: TextInputType.number,
                inputFormatters: [ThousandsSeparatorInputFormatter()],
                controller: montantController,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: "Montant",
                  hintText: "Saisissez le montant ici...",
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(8.0),
              child: TextFormField(
                keyboardType: TextInputType.text,
                controller: compteController,
                textInputAction: TextInputAction.done,
                decoration: const InputDecoration(
                  labelText: "Numéro de compte / Téléphone",
                  hintText: "Saisissez le Num de compte ici...",
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(15.0),
              child: ElevatedButton(
                onPressed: () async {
                  if (_validationSaisie()) {
                    _enregistrerDemandePaiement();
                  } else {
                    EasyLoading.showError(msgErr);
                  }
                },
                child: const Text("Enregistrer ma demande de paiement"),
              ),
            ),
            addVerticalSpace(10),
          ],
        ),
      ),
    );
  }

  _enregistrerDemandePaiement() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "montant": _montant,
        "mode": _mode,
        "compte": compteController.text.trim(),
        "id": demande.id ?? 0,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}enregistrer-demande-paiement'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              montantController.text = '';
              modePaiementController.text = '';
              compteController.text = '';
              _mode = 0;
              _montant = 0;
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
    if (compteController.text.trim() == "") {
      pass = false;
      msgErr = "Veuillez renseigner le numéro de compte";
    }
    if (montantController.text.trim() == "") {
      pass = false;
      msgErr = "Veuillez renseigner le montant";
    }else{
      _montant = double.parse(montantController.text.removeAllWhitespace.trim());
      if (_montant < 1000) {
        pass = false;
        msgErr = "Le montant du paiement dois être supérieur ou égale à 1000 Frs";
      }
    }
    if (_mode == 0) {
      pass = false;
      msgErr = "Veuillez choisir un mode de paiement";
    }
    return pass;
  }
}
