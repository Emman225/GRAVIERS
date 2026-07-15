import 'dart:convert';
import 'dart:io';

import 'package:camera_camera/camera_camera.dart';
import 'package:date_field/date_field.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:mon_gravier_com/globale.dart';

import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:mon_gravier_com/screens/edition_adresse/edition_adresse_screen.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../constants.dart';
import '../cart/components/check_out_card.dart';

class ChoixAdresseScreen extends StatefulWidget {
  static String routeName = "/choixAdresse";

  const ChoixAdresseScreen({super.key});

  @override
  State<ChoixAdresseScreen> createState() => _ChoixAdresseScreenState();
}

class _ChoixAdresseScreenState extends State<ChoixAdresseScreen> {
  AdresseDeLivraison adresses = AdresseDeLivraison();
  TextEditingController adresseController = TextEditingController();
  TextEditingController moyenPaiementController = TextEditingController();
  TextEditingController modePaiementController = TextEditingController();
  TextEditingController noteController = TextEditingController();
  TextEditingController typeLivraisonController = TextEditingController();
  TextEditingController numBlController = TextEditingController();
  TextEditingController blController = TextEditingController();

  TextEditingController banqueController = TextEditingController();
  TextEditingController numCompteController = TextEditingController();
  TextEditingController refController = TextEditingController();
  TextEditingController recuController = TextEditingController();
  String _dateOp = '';

  List<UneAdresse> _listAdresse = [];
  List<ModePaiements> _listMoyenPaiement = [];
  List _listModePaiement = [];
  List<TypeLivraisons> _listTypeLivraison = [];

  double _montantTotal = 0;
  final double _montantMaxLigne = 2000000;
  int _modePaiement = 0;
  UneAdresse _adresse = UneAdresse();
  ModePaiements _moyenPaiement = ModePaiements();
  TypeLivraisons _typeLivraison = TypeLivraisons();

  String _date = '';
  File? _bl;
  File? _vir;

  chargerAdresse() async {
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
            .post(Uri.parse('${lienAPI()}get-adresses'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          setState(() {
            adresses = AdresseDeLivraison.fromJson(datas);
            _listAdresse = adresses.data ?? [];
            // tva = adresses.tva ?? 0;
            // montantTva = getTotalAmount() * tva / 100;
          });
          if (kDebugMode) {
            print(_listAdresse);
          }
        }
      } catch (e) {
        user.code = 500;
        if (kDebugMode) {
          print(e.toString());
        }
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  openCamera(niveau) {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 100,
              child: Row(
                mainAxisAlignment: mainCenter,
                crossAxisAlignment: crossCenter,
                children: [
                  Column(
                    children: [
                      //*/ Fichier /*//
                      addVerticalSpace(2),
                      IconButton(
                        iconSize: 40,
                        onPressed: () async {
                          Get.back();

                          FilePickerResult? result =
                              await FilePicker.platform.pickFiles(
                            type: FileType.image,
                            dialogTitle: "Choisir une image",
                          );
                          if (result != null) {
                            final chemin = result.files.single.path;
                            File file = File(chemin!);
                            if (niveau == 1) {
                              _bl = await rognerImage(context, file.path);
                              blController.text = "1 Fichier";
                            } else {
                              _vir = await rognerImage(context, file.path);
                              recuController.text = "1 Fichier";
                            }
                            setState(() {});
                          }
                        },
                        icon: const Icon(
                          Icons.image,
                          color: kPrimaryColor,
                        ),
                      ),
                      const Text(
                        "Existante",
                        style: black16BoldTextStyle,
                        textAlign: TextAlign.center,
                      ),
                      //*/ ------- /*//
                    ],
                  ),
                  addHorizontalSpace(50),
                  Column(
                    children: [
                      //*/ Camera /*//
                      addVerticalSpace(2),
                      IconButton(
                        iconSize: 40,
                        onPressed: () async {
                          await Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => CameraCamera(
                                        onFile: (file) async {
                                          if (niveau == 1) {
                                            _bl = await rognerImage(
                                                context, file.path);
                                            blController.text = "1 Fichier";
                                          } else {
                                            _vir = await rognerImage(
                                                context, file.path);
                                            recuController.text = "1 Fichier";
                                          }
                                          Navigator.pop(context);
                                          setState(() {});
                                        },
                                      )));

                          Get.back();
                        },
                        icon: const Icon(
                          Icons.camera,
                          color: kPrimaryColor,
                        ),
                      ),
                      const Text(
                        "Nouvelle",
                        style: black16BoldTextStyle,
                        textAlign: TextAlign.center,
                      ),
                      //*/ ------- /*//
                    ],
                  ),
                ],
              ),
            ),
          );
        });
  }

  @override
  void initState() {
    _montantTotal = getTotalAmount();
    // « Paiement en agence » (id 3) = paiement HORS LIGNE : la commande est
    // enregistrée et payée ensuite en agence (le backend ne déclenche la
    // passerelle que pour le mode « En ligne » id 1).
    if (_montantTotal > _montantMaxLigne) {
      _listModePaiement = [
        {"id": 2, "libelle": "Virement bancaire"},
        {"id": 3, "libelle": "Paiement en agence"},
      ];
      _modePaiement = 2;
    } else {
      _listModePaiement = [
        {"id": 1, "libelle": "En ligne"},
        {"id": 3, "libelle": "Paiement en agence"},
      ];
      _modePaiement = 1;
    }

    _adresse = UneAdresse();
    _moyenPaiement = ModePaiements();
    adresseController = TextEditingController();
    moyenPaiementController = TextEditingController();
    noteController = TextEditingController();
    typeLivraisonController = TextEditingController();
    numBlController = TextEditingController();
    blController = TextEditingController();
    modePaiementController = TextEditingController();
    banqueController = TextEditingController();
    numCompteController = TextEditingController();
    refController = TextEditingController();
    recuController = TextEditingController();
    _listMoyenPaiement = user.configs?.modePaiements ?? [];
    _listTypeLivraison = user.configs?.typeLivraisons ?? [];
    _typeLivraison = _listTypeLivraison.firstWhere((elt) => elt.id == 1);

    DateTime today = DateTime.now();
    DateTime nextWeek = today.add(const Duration(days: 7));
    _date = DateFormat('yyyy-MM-dd').format(nextWeek);

    _dateOp = DateFormat('yyyy-MM-dd').format(today);

    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != "") {
        chargerAdresse();
      }
    });
  }

  @override
  void dispose() {
    adresseController.dispose();
    moyenPaiementController.dispose();
    noteController.dispose();
    typeLivraisonController.dispose();
    numBlController.dispose();
    blController.dispose();
    modePaiementController.dispose();
    banqueController.dispose();
    numCompteController.dispose();
    refController.dispose();
    recuController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Autres Informations",
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
                  if (_montantTotal > _montantMaxLigne) ...[
                    Row(
                      mainAxisAlignment: mainCenter,
                      crossAxisAlignment: crossCenter,
                      children: [
                        const Icon(Icons.info, color: redColor, size: 30),
                        addHorizontalSpace(10),
                        Text(
                          "Pour les montants supérieur à ${formaterMontant(_montantMaxLigne)}, \nvous devez: faire un virement bancaire, \npayer en agence, \neffectuer plusieurs commandes",
                          style: const TextStyle(color: redColor, fontSize: 12),
                        )
                      ],
                    ),
                    addVerticalSpace(10),
                  ],
                  if (paniers.first.product.type_affaire == VENTE) ...[
                    Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: DropDownTextField(
                        textEditingController: typeLivraisonController,
                        title: 'Type de livraison',
                        hint: 'Choisir votre type de livraison',
                        options: {
                          for (var p in _listTypeLivraison)
                            p.id ?? 0: p.libelle.toString()
                        },
                        multiple: false,
                        selectedOptions: const [1],
                        textInputAction: TextInputAction.next,
                        onChanged: (selectedIds) {
                          setState(() {
                            int id = selectedIds?.first ?? 0;
                            _typeLivraison = _listTypeLivraison.firstWhere((elt) => elt.id == id);
                            adresseController.text = '';
                          });
                        },
                      ),
                    ),
                    if (meFaireLivre) ...[
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: DateTimeFormField(
                          decoration: const InputDecoration(
                            hintStyle: TextStyle(color: Colors.black45),
                            errorStyle: TextStyle(color: Colors.redAccent),
                            border: OutlineInputBorder(),
                            suffixIcon: Icon(Icons.event_note),
                            labelText: 'Date de livraison',
                          ),
                          initialValue: DateTime.parse(_date),
                          use24hFormat: true,
                          mode: DateTimeFieldPickerMode.date,
                          dateFormat: DateFormat('dd-MM-yyyy'),
                          autovalidateMode: AutovalidateMode.always,
                          onDateSelected: (DateTime value) {
                            _date = DateFormat('yyyy-MM-dd').format(value);
                          },
                        ),
                      ),
                    ]
                  ],
                  if (meFaireLivre) ...[
                    Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: DropDownTextField(
                        textEditingController: adresseController,
                        title: 'Adresse de livraison',
                        hint: 'Choisir votre adresse de livraison',
                        options: {
                          for (var p in _listAdresse)
                            p.id ?? 0: p.affichage.toString()
                        },
                        multiple: false,
                        selectedOptions: [_adresse.id ?? 0],
                        textInputAction: TextInputAction.next,
                        onChanged: (selectedIds) {
                          setState(() {
                            int id = selectedIds?.first ?? 0;
                            _adresse = _listAdresse.firstWhere((elt) => elt.id == id);
                          });
                        },
                      ),
                    ),
                  ],
                  if (user.clientATerme == false || user.token == null) ...[
                    Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: DropDownTextField(
                        textEditingController: modePaiementController,
                        title: 'Mode de paiement',
                        hint: 'Choisir un mode de paiement',
                        options: {
                          for (var p in _listModePaiement)
                            p["id"] ?? 0: p["libelle"]
                        },
                        selectedOptions: [_modePaiement],
                        multiple: false,
                        textInputAction: TextInputAction.next,
                        onChanged: (selectedIds) {
                          setState(() {
                            _modePaiement = selectedIds?.first ?? 0;
                          });
                        },
                      ),
                    ),
                    if (_modePaiement == 1) ...[
                      //Paiement en ligne
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: DropDownTextField(
                          textEditingController: moyenPaiementController,
                          title: 'Moyen de paiement',
                          hint: 'Choisir un moyen de paiement',
                          options: {
                            for (var p in _listMoyenPaiement)
                              p.id ?? 0: p.libelle.toString()
                          },
                          multiple: false,
                          textInputAction: TextInputAction.next,
                          onChanged: (selectedIds) {
                            setState(() {
                              int id = selectedIds?.first ?? 0;
                              _moyenPaiement = _listMoyenPaiement.firstWhere((elt) => elt.id == id);
                            });
                          },
                        ),
                      ),
                    ] else if (_modePaiement == 2) ...[
                      //Virement ou chèque
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: TextFormField(
                          keyboardType: TextInputType.text,
                          controller: banqueController,
                          textInputAction: TextInputAction.next,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: "Banque",
                            hintText: "Saisissez la banque ici...",
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: TextFormField(
                          keyboardType: TextInputType.text,
                          controller: numCompteController,
                          textInputAction: TextInputAction.next,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            labelText: "Numéro de compte",
                            hintText: "Saisissez le N° de compte ici...",
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: TextFormField(
                          keyboardType: TextInputType.text,
                          controller: refController,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: "Référence opérations",
                            hintText: "Saisissez la référence ici...",
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: DateTimeFormField(
                          decoration: const InputDecoration(
                            hintStyle: TextStyle(color: Colors.black45),
                            errorStyle: TextStyle(color: Colors.redAccent),
                            border: OutlineInputBorder(),
                            suffixIcon: Icon(Icons.event_note),
                            labelText: 'Date de l\'opération',
                          ),
                          initialValue: DateTime.parse(
                              DateFormat('yyyy-MM-dd').format(DateTime.now())),
                          use24hFormat: true,
                          mode: DateTimeFieldPickerMode.date,
                          dateFormat: DateFormat('dd-MM-yyyy'),
                          autovalidateMode: AutovalidateMode.always,
                          onDateSelected: (DateTime value) {
                            _dateOp = DateFormat('yyyy-MM-dd').format(value);
                          },
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(8.0),
                        child: TextFormField(
                          onTap: () => openCamera(2),
                          readOnly: true,
                          keyboardType: TextInputType.text,
                          controller: recuController,
                          textInputAction: TextInputAction.next,
                          decoration: const InputDecoration(
                            labelText: "Reçu de l'opération",
                            hintText: "Chargez le reçu ici...",
                          ),
                        ),
                      ),
                    ],
                  ],
                  if (paniers.first.product.type_affaire == VENTE &&
                      user.code_parrain == ENTREPRISE) ...[
                    Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: TextFormField(
                        keyboardType: TextInputType.text,
                        controller: numBlController,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: "N°Bon de commande interne",
                          hintText: "Saisissez le numéro ici...",
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(8.0),
                      child: TextFormField(
                        onTap: () => openCamera(1),
                        readOnly: true,
                        keyboardType: TextInputType.text,
                        controller: blController,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: "Votre bon de commande",
                          hintText: "Chargez le bon de cde ici...",
                        ),
                      ),
                    ),
                  ],
                  Padding(
                    padding: const EdgeInsets.all(8.0),
                    child: TextFormField(
                      keyboardType: TextInputType.text,
                      controller: noteController,
                      textInputAction: TextInputAction.done,
                      decoration: const InputDecoration(
                        labelText: "Note supplémentaire (optionnelle)",
                        hintText: "Saisissez un commentaire ici...",
                      ),
                    ),
                  ),
                  addVerticalSpace(10),
                ],
              ),
            ),
      floatingActionButton:
          (user.token != null && user.token != "" && meFaireLivre == true)
              ? FloatingActionButton.extended(
                  backgroundColor: const Color(0xff03dac6),
                  foregroundColor: Colors.black,
                  onPressed: () async {
                    var add = await Get.toNamed(EditionAdresseScreen.routeName,
                        arguments: UneAdresse());
                    await chargerAdresse();
                    if (add != null) {
                      setState(() {
                        _adresse = add;
                        adresseController.text = _adresse.affichage ?? '';
                      });
                    }
                  },
                  icon: const Icon(Icons.add),
                  label: const Text('Ajouter Adresse'),
                )
              : null,
      bottomNavigationBar: CheckoutCard(niveau: 2, data: [
              _adresse,
              _moyenPaiement,
              noteController.text.trim(),
              _date,
              _typeLivraison,
              numBlController.text.trim(),
              _bl,
              _modePaiement,
              banqueController.text.trim(),
              numCompteController.text.trim(),
              refController.text.trim(),
              _dateOp,
              _vir,
            ]),
    );
  }
}
