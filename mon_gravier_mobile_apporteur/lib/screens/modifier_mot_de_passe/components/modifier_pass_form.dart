import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/screens/sign_in/sign_in_screen.dart';

import '../../../components/custom_surfix_icon.dart';

class ModifierPassForm extends StatefulWidget {
  const ModifierPassForm({super.key});

  @override
  _ModifierPassFormState createState() => _ModifierPassFormState();
}

class _ModifierPassFormState extends State<ModifierPassForm> {
  final _formKey = GlobalKey<FormState>();
  TextEditingController confirmPassController = TextEditingController();
  TextEditingController newPassController = TextEditingController();
  TextEditingController passwordController = TextEditingController();

  /// 1 = changement volontaire depuis le profil (l'ancien mot de passe est exigé)
  /// 2 = réinitialisation après validation du code « mot de passe oublié »
  ///     (l'utilisateur ne connaît justement PAS son mot de passe actuel)
  ///
  /// Les deux écrans appelants transmettaient déjà cette valeur (profil -> 1,
  /// écran du code -> 2), mais le formulaire ne la lisait pas : il affichait
  /// toujours le champ « Mot de passe actuel » et envoyait niveau 1.
  int niveau = 1;

  bool get estReinitialisation => niveau != 1;

  @override
  void initState() {
    confirmPassController = TextEditingController();
    newPassController = TextEditingController();
    passwordController = TextEditingController();
    final arg = Get.arguments;
    niveau = (arg is int) ? arg : 1;
    super.initState();
  }

  @override
  void dispose() {
    confirmPassController.dispose();
    newPassController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [
          const SizedBox(height: 20),
          // Masqué en réinitialisation : demander le mot de passe actuel à quelqu'un
          // qui vient de déclarer l'avoir oublié n'a pas de sens.
          if (!estReinitialisation) ...[
            TextFormField(
              controller: passwordController,
              obscureText: true,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: "Mot de passe actuel *",
                hintText: "Entrez votre Mot de passe actuel",
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Lock.svg"),
              ),
            ),
            const SizedBox(height: 20),
          ],
          TextFormField(
            controller: newPassController,
            obscureText: true,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: "Nouveau mot de passe *",
              hintText: "Entrez votre nouveau mot de passe",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Lock.svg"),
            ),
          ),
          const SizedBox(height: 20),
          TextFormField(
            controller: confirmPassController,
            obscureText: true,
            textInputAction: TextInputAction.done,
            decoration: const InputDecoration(
              labelText: "Confirmation mot de passe *",
              hintText: "Confirmez votre mot de passe",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Lock.svg"),
            ),
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: () async {
              if (_validationSaisie()) {
                modifierPass();
              }else{
                afficherErreur(msgErr);
              }
            },
            child: const Text("Modifier mes accès"),
          ),
        ],
      ),
    );
  }

  _validationSaisie() {
    bool pass = true;
    // L'ancien mot de passe n'est exigé QUE pour un changement volontaire.
    if (!estReinitialisation && passwordController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir l'ancien mot de passe";
    } else if (newPassController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir le nouveau mot de passe";
    } else if (confirmPassController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir le mot de passe de confirmation";
    } else if (confirmPassController.text.trim() != newPassController.text.trim()) {
      pass = false;
      msgErr = "Les mots de passe ne correspondent pas";
    }
    return pass;
  }

  modifierPass() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "old": passwordController.text.trim(),
        "new": newPassController.text.trim(),
        // Niveau réellement demandé par l'écran appelant (1 = profil, 2 = après code).
        // Il était codé en dur à 1 : le serveur exigeait donc l'ancien mot de passe
        // même dans le parcours « mot de passe oublié ».
        "niveau": niveau,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}modifier-pass-apporteur'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print('modifier-pass-apporteur status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (datas['code'] == 200) {
            setState(() {
              confirmPassController.text = '';
              newPassController.text = '';
              passwordController.text = '';
            });
            fermerChargement();
            afficherSucces(datas['message'] ?? 'Mot de passe modifié');
            // Après une RÉINITIALISATION, l'apporteur n'est pas encore réellement
            // connecté avec ce nouveau mot de passe : on le ramène à la connexion.
            if (estReinitialisation) {
              Get.offAllNamed(SignInScreen.routeName);
            }
            return;
          }else{
            afficherErreur(datas['message'] ?? "La modification a échoué.");
          }
        } else {
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        if (kDebugMode) {
          print(e.toString());
        }
        // Le bloc de secours n'alimentait que des variables jamais lues : en cas de
        // panne réseau, l'écran restait muet.
        afficherErreur(
            "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.");
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
