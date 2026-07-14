import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/globale.dart';

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

  @override
  void initState() {
    confirmPassController = TextEditingController();
    newPassController = TextEditingController();
    passwordController = TextEditingController();
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
          TextFormField(
            controller: passwordController,
            obscureText: true,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: "Mot de passe actuel",
              hintText: "Entrez votre Mot de passe actuel",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Lock.svg"),
            ),
          ),
          const SizedBox(height: 20),
          TextFormField(
            controller: newPassController,
            obscureText: true,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: "Nouveau mot de passe",
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
              labelText: "Confirmation mot de passe",
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
                EasyLoading.showError(msgErr);
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
    if (confirmPassController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir le mot de passe de confirmation";
    }if (newPassController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir le nouveau mot de passe";
    }if (passwordController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir l'ancien mot de passe";
    }if (confirmPassController.text.trim() != newPassController.text.trim()) {
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
        "niveau": 1,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}modifier-pass-livreur'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              confirmPassController.text = '';
              newPassController.text = '';
              passwordController.text = '';
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
}
