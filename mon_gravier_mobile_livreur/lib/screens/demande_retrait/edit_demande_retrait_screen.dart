import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';
import 'package:select_searchable_list/select_searchable_list.dart';
import '../../../components/bouton_retour.dart';
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
  /// Verrou anti double envoi : plusieurs demandes de paiement identiques
  /// etaient creees quand l'utilisateur ne voyait aucune confirmation.
  bool _envoiEnCours = false;

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
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: Container(
        width: double.infinity,
        height: heightOfScreen(context),
        child: ListView(
          physics: const BouncingScrollPhysics(),
          children: [
            addVerticalSpace(20),
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
              child: TextFormField(
                keyboardType: TextInputType.number,
                inputFormatters: [ThousandsSeparatorInputFormatter()],
                controller: montantController,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: "Montant *",
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
                  labelText: "Numéro de compte / Téléphone *",
                  hintText: "Saisissez le Num de compte ici...",
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(15.0),
              child: ElevatedButton(
                onPressed: _envoiEnCours ? null : () async {
                  if (_validationSaisie()) {
                    _enregistrerDemandePaiement();
                  } else {
                    afficherErreur(msgErr);
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
    if (_envoiEnCours) return;
    if (await verifierConnexion()) {
      setState(() => _envoiEnCours = true);
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
        final http.Response retourHttp = await http
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
            afficherSucces(datas['message']);
            // Le solde et la liste des reglements de l'accueil changent.
            rafraichirAccueil?.call();
          }else{
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        // Ce bloc de secours n.affichait RIEN : l.ecran restait muet en cas de
        // coupure reseau ou de reponse illisible.
        afficherErreur("Impossible de contacter le serveur. Verifiez votre connexion et reessayez.");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (mounted) setState(() => _envoiEnCours = false);
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
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
      // double.parse levait une FormatException NON capturee (virgule decimale du
      // clavier fr, caractere parasite) : le bouton ne faisait alors RIEN.
      final saisie = montantController.text
          .replaceAll(RegExp(r"[^0-9,.]"), "")
          .replaceAll(",", ".");
      final valeur = double.tryParse(saisie);
      if (valeur == null) {
        pass = false;
        msgErr = "Veuillez saisir un montant valide";
      } else {
        _montant = valeur;
        final solde = user.livreur?.solde ?? 0;
        if (_montant < 1000) {
          pass = false;
          msgErr = "Le montant du paiement dois être supérieur ou égale à 1000 Frs";
        } else if (_montant > solde) {
          // Controle absent cote application : le serveur le refusait a la
          // creation mais PAS a la modification.
          pass = false;
          msgErr = "Votre solde est insuffisant (disponible : ${formaterMontant(solde.toDouble())})";
        }
      }
    }
    if (_mode == 0) {
      pass = false;
      msgErr = "Veuillez choisir un mode de paiement";
    }
    return pass;
  }
}
