import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;

import '../../../components/custom_surfix_icon.dart';
import '../../../components/form_error.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/User.dart';
import '../../otp/otp_screen.dart';

class ForgotPassForm extends StatefulWidget {
  const ForgotPassForm({super.key});

  @override
  _ForgotPassFormState createState() => _ForgotPassFormState();
}

class _ForgotPassFormState extends State<ForgotPassForm> {
  final _formKey = GlobalKey<FormState>();
  List<String> errors = [];
  String? email;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [
          TextFormField(
            keyboardType: TextInputType.emailAddress,
            onSaved: (newValue) => email = newValue,
            onChanged: (value) {
              if (value.isNotEmpty && errors.contains(kEmailNullError)) {
                setState(() {
                  errors.remove(kEmailNullError);
                });
              } else if (emailValidatorRegExp.hasMatch(value) &&
                  errors.contains(kInvalidEmailError)) {
                setState(() {
                  errors.remove(kInvalidEmailError);
                });
              }
              return;
            },
            validator: (value) {
              if (value!.isEmpty && !errors.contains(kEmailNullError)) {
                setState(() {
                  errors.add(kEmailNullError);
                });
              } else if (!emailValidatorRegExp.hasMatch(value) &&
                  !errors.contains(kInvalidEmailError)) {
                setState(() {
                  errors.add(kInvalidEmailError);
                });
              }
              return null;
            },
            decoration: const InputDecoration(
              labelText: "Email",
              hintText: "Saisir votre adresse mail",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Mail.svg"),
            ),
          ),
          const SizedBox(height: 8),
          FormError(errors: errors),
          const SizedBox(height: 8),
          ElevatedButton(
            onPressed: () async {
              if (_formKey.currentState!.validate()) {
                _formKey.currentState!.save();
                // Do what you want to do
                if (await verifierConnexion()) {
                  afficherChargement();

                  var param = {
                    "email": email,
                  };

                  if (kDebugMode) {
                    print(param);
                  }

                  try {
                    retourHttp = await http
                        .post(Uri.parse('${lienAPI()}demandeReinititPass'),
                            headers: {"Content-Type": "application/json"},
                            body: jsonEncode(param))
                        .timeout(const Duration(minutes: 2));
                    if (kDebugMode) {
                      print('demandeReinititPass status: ${retourHttp.statusCode}');
                    }
                    if (retourHttp.statusCode == 200) {
                      var datas = jsonDecode(retourHttp.body);
                      if (kDebugMode) {
                        print(retourHttp.body);
                      }
                      if (datas['code'] == 200) {
                        user = User.fromJson(datas);
                        Get.toNamed(OtpScreen.routeName, arguments: 2);
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
              }
            },
            child: const Text("Continuer"),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}
