import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_apporteur/screens/init_screen.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/screens/modifier_mot_de_passe/modifier_pass_screen.dart';

import '../../../constants.dart';
import '../../../globale.dart';

class OtpForm extends StatefulWidget {
  final int niveau;

  const OtpForm({
    Key? key,
    required this.niveau,
  }) : super(key: key);

  @override
  _OtpFormState createState() => _OtpFormState();
}

class _OtpFormState extends State<OtpForm> {
  FocusNode? pin2FocusNode;
  FocusNode? pin3FocusNode;
  FocusNode? pin4FocusNode;

  TextEditingController n1Controller = TextEditingController();
  TextEditingController n2Controller = TextEditingController();
  TextEditingController n3Controller = TextEditingController();
  TextEditingController n4Controller = TextEditingController();

  @override
  void initState() {
    n1Controller = TextEditingController();
    n2Controller = TextEditingController();
    n3Controller = TextEditingController();
    n4Controller = TextEditingController();
    super.initState();
    pin2FocusNode = FocusNode();
    pin3FocusNode = FocusNode();
    pin4FocusNode = FocusNode();
  }

  @override
  void dispose() {
    n1Controller.dispose();
    n2Controller.dispose();
    n3Controller.dispose();
    n4Controller.dispose();
    super.dispose();
    pin2FocusNode!.dispose();
    pin3FocusNode!.dispose();
    pin4FocusNode!.dispose();
  }

  void nextField(String value, FocusNode? focusNode) {
    if (value.length == 1) {
      focusNode!.requestFocus();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      child: Column(
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.09),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              SizedBox(
                width: 60,
                child: TextFormField(
                  controller: n1Controller,
                  autofocus: true,
                  obscureText: true,
                  style: const TextStyle(fontSize: 24),
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: otpInputDecoration,
                  onChanged: (value) {
                    nextField(value, pin2FocusNode);
                  },
                ),
              ),
              SizedBox(
                width: 60,
                child: TextFormField(
                  controller: n2Controller,
                  focusNode: pin2FocusNode,
                  obscureText: true,
                  style: const TextStyle(fontSize: 24),
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: otpInputDecoration,
                  onChanged: (value) => nextField(value, pin3FocusNode),
                ),
              ),
              SizedBox(
                width: 60,
                child: TextFormField(
                  controller: n3Controller,
                  focusNode: pin3FocusNode,
                  obscureText: true,
                  style: const TextStyle(fontSize: 24),
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: otpInputDecoration,
                  onChanged: (value) => nextField(value, pin4FocusNode),
                ),
              ),
              SizedBox(
                width: 60,
                child: TextFormField(
                  controller: n4Controller,
                  focusNode: pin4FocusNode,
                  obscureText: true,
                  style: const TextStyle(fontSize: 24),
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  decoration: otpInputDecoration,
                  onChanged: (value) {
                    if (value.length == 1) {
                      pin4FocusNode!.unfocus();
                    }
                  },
                ),
              ),
            ],
          ),
          SizedBox(height: MediaQuery.of(context).size.height * 0.09),
          ElevatedButton(
            onPressed: () async {
              if (await verifierConnexion()) {
                afficherChargement();

                final otp = n1Controller.text.trim() +
                    n2Controller.text.trim() +
                    n3Controller.text.trim() +
                    n4Controller.text.trim();

                var param = {
                  "access": user.token.toString(),
                  "type": user.type.toString(),
                  "otp": otp,
                  "niveau": widget.niveau,
                };

                if (kDebugMode) {
                  print(param);
                }

                try {
                  retourHttp = await http
                      .post(Uri.parse('${lienAPI()}verifierOtp'),
                          headers: {"Content-Type": "application/json"},
                          body: jsonEncode(param))
                      .timeout(const Duration(minutes: 2));
                  if (kDebugMode) {
                    print('verifierOtp status: ${retourHttp.statusCode}');
                  }
                  if (retourHttp.statusCode == 200) {
                    var datas = jsonDecode(retourHttp.body);
                    if (datas['code'] == 200) {
                      setState(() {
                        n1Controller.text = "";
                        n2Controller.text = "";
                        n3Controller.text = "";
                        n4Controller.text = "";
                      });
                      switch (widget.niveau) {
                        case 1:
                          Get.toNamed(InitScreen.routeName);
                          break;
                        case 2:
                          Get.toNamed(ModifierPasseScreen.routeName, arguments: 2);
                          break;
                        default:
                      }

                    } else {
                      EasyLoading.showError(datas['message']);
                    }
                  } else {
                    EasyLoading.showError("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
                  }
                } catch (e) {
                  user.code = 500;
                  user.message =
                      "Une erreur s'est produite veuillez reesayer plus tard";
                  if (kDebugMode) {
                    print(e.toString());
                  }
                }
                fermerChargement();
              } else {
                EasyLoading.showInfo(
                    "Veuillez vérifier votre connexion internet");
              }
            },
            child: const Text("Continuer"),
          ),
        ],
      ),
    );
  }
}
