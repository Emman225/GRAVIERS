import 'dart:convert';

import 'package:date_field/date_field.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/globale.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';
import 'package:mon_gravier_com/models/demande_livraison.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison/details_demande_livraison_screen.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../components/bouton_retour.dart';
import '../../components/custom_surfix_icon.dart';
import '../../models/ConfigModel.dart';
import '../edition_adresse/edition_adresse_screen.dart';

class DemandeLivraisonScreen extends StatefulWidget {
  static String routeName = "/demande-livraison";

  const DemandeLivraisonScreen({super.key});

  @override
  State<DemandeLivraisonScreen> createState() => _DemandeLivraisonScreenState();
}

class _DemandeLivraisonScreenState extends State<DemandeLivraisonScreen> {
  AdresseDeLivraison adresses = AdresseDeLivraison();
  TextEditingController adresseControllerDep = TextEditingController();
  TextEditingController adresseControllerArr = TextEditingController();
  TextEditingController modePaiementController = TextEditingController();
  TextEditingController noteController = TextEditingController();
  TextEditingController typeLivraisonController = TextEditingController();
  List<UneAdresse> _listAdresse = [];
  List<ModePaiements> _listModePaiement = [];
  List<TypeLivraisons> _listTypeLivraison = [];

  int _adresseDep = 0;
  int _adresseArr = 0;
  int _mode = 0;
  int _typeLivraison = 0;

  String _date = DateFormat('yyyy-MM-dd').format(DateTime.now());

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
          });
          if (kDebugMode) {
            print(_listAdresse);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        if (kDebugMode) {
          print(e.toString());
        }
        afficherErreur(messageErreurTechnique(e));
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    _adresseDep = 0;
    _adresseArr = 0;
    _mode = 0;
    _typeLivraison = 0;
    adresseControllerDep = TextEditingController();
    adresseControllerArr = TextEditingController();
    modePaiementController = TextEditingController();
    noteController = TextEditingController();
    typeLivraisonController = TextEditingController();
    // Le reglement au guichet doit etre offert ici comme il l'est sur le
    // site : ce champ est le SEUL choix de paiement de la demande, alors que
    // l'ecran de commande en a deux. Sans lui, la seule facon de commander un
    // transport etait de payer immediatement par mobile money.
    //
    // Repli sur l'ancienne liste si le serveur ne sert pas encore la
    // nouvelle : l'ecran continue de fonctionner, sans le guichet.
    final modesLivraison = user.configs?.modePaiementsLivraison;
    _listModePaiement = (modesLivraison != null && modesLivraison.isNotEmpty)
        ? modesLivraison
        : (user.configs?.modePaiements ?? []);
    _listTypeLivraison = user.configs?.typeLivraisons ?? [];
    demandeLivraison = DemandeLivraison();
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerAdresse();
    });
  }

  @override
  void dispose() {
    adresseControllerDep.dispose();
    adresseControllerArr.dispose();
    modePaiementController.dispose();
    noteController.dispose();
    typeLivraisonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Demande de livraison",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () async {
          await Get.toNamed(EditionAdresseScreen.routeName,
              arguments: UneAdresse());
          // Même précaution que sur la liste des devis : cette attente se
          // termine aussi quand toute la pile d'écrans est retirée.
          if (mounted) {
            chargerAdresse();
          }
        },
        icon: const Icon(Icons.add),
        label: const Text('Ajouter Adresse'),
      ),
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.all(8.0),
        child: ElevatedButton(
          onPressed: () async {
            if (_validationSaisie()) {
              demandeLivraison = DemandeLivraison(
                adresseDepart: _adresseDep,
                adresseDestination: _adresseArr,
                dateLivraison: _date,
                modePaiement: _mode,
                note: noteController.text.trim(),
                typeLivraison: _typeLivraison,
                produits: [],
              );
              Get.toNamed(DetailsDemandeLivraisonScreen.routeName);
            } else {
              afficherErreur(msgErr);
            }
          },
          child: const Text("Suivant"),
        ),
      ),
      body: ListView(
        physics: const BouncingScrollPhysics(),
        children: [
          addVerticalSpace(20),
          Image.asset('assets/images/livraison.jpg',
              width: widthOfScreen(context)),
          const SizedBox(height: 20),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: typeLivraisonController,
              title: 'Type de livraison *',
              hint: 'Choisir votre type de livraison',
              options: {
                for (var p in _listTypeLivraison)
                  p.id ?? 0: p.libelle.toString()
              },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  _typeLivraison = selectedIds?.first ?? 0;
                  adresseControllerDep.text = '';
                  adresseControllerArr.text = '';
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: adresseControllerDep,
              title: 'Adresse départ *',
              hint: 'Choisir votre adresse',
              options: {
                for (var p in _listAdresse) p.id ?? 0: p.affichage.toString()
              },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  _adresseDep = selectedIds?.first ?? 0;
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: adresseControllerArr,
              title: 'Adresse destination *',
              hint: 'Choisir votre adresse',
              options: {
                for (var p in _listAdresse) p.id ?? 0: p.affichage.toString()
              },
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  _adresseArr = selectedIds?.first ?? 0;
                });
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: DropDownTextField(
              textEditingController: modePaiementController,
              title: 'Mode de paiement *',
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
            child: DateTimeFormField(
              decoration: const InputDecoration(
                hintStyle: TextStyle(color: Colors.black45),
                errorStyle: TextStyle(color: Colors.redAccent),
                border: OutlineInputBorder(),
                suffixIcon: Icon(Icons.event_note),
                labelText: 'Date de livraison *',
                // Le délai toléré (lot 81, 15/09/2026), lu en choisissant la date.
                helperText: kMentionDelaiLivraison,
                helperMaxLines: 3,
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
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.text,
              maxLines: 3,
              controller: noteController,
              textInputAction: TextInputAction.done,
              decoration: const InputDecoration(
                labelText: "Note supplémentaire",
                hintText: "Saisissez un commentaire ici...",
                // If  you are using latest version of flutter then lable text and hint text shown like this
                // if you r using flutter less then 1.20.* then maybe this is not working properly
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(
                    svgIcon: "assets/icons/Conversation.svg"),
              ),
            ),
          ),
        ],
      ),
    );
  }

  _validationSaisie() {
    bool pass = true;
    if (_typeLivraison == 0) {
      pass = false;
      msgErr = "Veuillez choisir le type de livraison";
    }
    if (_adresseDep == 0) {
      pass = false;
      msgErr = "Veuillez renseigner une adresse de départ valide";
    }
    if (_adresseArr == 0) {
      pass = false;
      msgErr = "Veuillez renseigner une adresse de destination valide";
    }
    if (_adresseDep == _adresseArr) {
      pass = false;
      msgErr = "L'adresse de départ et d'arrivé doivent être différente";
    }
    if (_mode == 0) {
      pass = false;
      msgErr = "Veuillez choisir le mode de paiement";
    }
    if (_date == "") {
      pass = false;
      msgErr = "Veuillez choisir la date de livraison";
    }
    return pass;
  }
}
