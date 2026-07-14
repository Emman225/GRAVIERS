import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../../components/custom_surfix_icon.dart';
import '../../../components/form_error.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/ConfigModel.dart';
import '../../../models/User.dart';
import '../../otp/otp_screen.dart';

class SignUpForm extends StatefulWidget {
  const SignUpForm({super.key});

  @override
  _SignUpFormState createState() => _SignUpFormState();
}

class _SignUpFormState extends State<SignUpForm> {
  final _formKey = GlobalKey<FormState>();
  String? nom;
  String? email;
  String? telephone;
  String? codeParain;
  String? password;
  String type_client = "1";
  String? confirm_password;
  String? rccm;
  String? ncc;
  bool remember = false;
  final List<String?> errors = [];
  bool _isPasswordVisible1 = false;
  bool _isPasswordVisible2 = false;
  List<Pays> pays = [];
  List<Ville> villesTot = [];
  List<Ville> villes = [];
  TextEditingController paysController = TextEditingController();
  TextEditingController villeController = TextEditingController();
  int pays_id = 1, ville_id = 1;
  File? dfeFile;
  File? rcFile;
  String? dfeFileName;
  String? rcFileName;

  @override
  void dispose() {
    paysController.dispose();
    villeController.dispose();
    super.dispose();
  }

  List<DropdownMenuItem<String>> get comboTypeClient {
    List<DropdownMenuItem<String>> menuItems = [
      const DropdownMenuItem(value: "1", child: Text("Particulier")),
      const DropdownMenuItem(value: "2", child: Text("Entreprise")),
    ];
    return menuItems;
  }

  void addError({String? error}) {
    if (!errors.contains(error)) {
      setState(() {
        errors.add(error);
      });
    }
  }

  void removeError({String? error}) {
    if (errors.contains(error)) {
      setState(() {
        errors.remove(error);
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _chargerConfigs();
  }

  _chargerConfigs() async {
    try {
      if (await verifierConnexion()) {
        retourHttp = await http
            .get(Uri.parse('${lienAPI()}get-config'))
            .timeout(const Duration(minutes: 2));
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          user.configs = ConfigModel.fromJson(datas);
          setState(() {
            pays = user.configs?.pays ?? [];
            villesTot = user.configs?.villes ?? [];
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        print('Erreur chargement configs: $e');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [

          Container(
            width: double.infinity,
            height: 65,
            decoration: BoxDecoration(
                color: Colors.transparent,
                border: Border.all(color: blackColor, width: 1),
                borderRadius: BorderRadius.circular(30)),
            padding: const EdgeInsets.all(20 * 0.3),
            child: DropdownButton(
              icon: const Icon(Icons.person_add_outlined, color: kPrimaryColor),
              alignment: AlignmentDirectional.bottomStart,
              isExpanded: true,
              value: type_client,
              items: comboTypeClient,
              onChanged: (String? value) {
                setState(() {
                  type_client = value!;
                });
              },
            ),
          ),

          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.all(5.0),
            child: DropDownTextField(
              textEditingController: paysController,
              title: 'Pays',
              hint: 'Choisir votre pays',
              options: {for (var p in pays) p.id ?? 0: p.nom.toString()},
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
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.all(5.0),
            child: DropDownTextField(
              textEditingController: villeController,
              title: 'Ville',
              hint: 'Choisir votre ville',
              options: {for (var p in villes) p.id ?? 0: p.nom.toString()},
              multiple: false,
              textInputAction: TextInputAction.next,
              onChanged: (selectedIds) {
                setState(() {
                  ville_id = selectedIds?.first ?? 1;
                });
              },
            ),
          ),

          const SizedBox(height: 10),
          TextFormField(
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => nom = newValue,
            textCapitalization: TextCapitalization.characters,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kNamelNullError);
              }
              return;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kNamelNullError);
                return "";
              }
              return null;
            },
            decoration: InputDecoration(
              labelText: type_client == '1' ? "Nom & Prénoms" : "Raison social",
              hintText: type_client == '1' ? "Entrez votre nom & prénoms" : "Entrez votre raison social",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: const CustomSurffixIcon(svgIcon: "assets/icons/User.svg"),
            ),
          ),

          const SizedBox(height: 10),
          TextFormField(
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => telephone = newValue,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kPhoneNumberNullError);
              }
              return;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kPhoneNumberNullError);
                return "";
              }
              return null;
            },
            decoration: const InputDecoration(
              labelText: "Téléphone",
              hintText: "Entrez votre téléphone",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Phone.svg"),
            ),
          ),

          const SizedBox(height: 10),
          TextFormField(
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => email = newValue,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kEmailNullError);
              } else if (emailValidatorRegExp.hasMatch(value)) {
                removeError(error: kInvalidEmailError);
              }
              return;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kEmailNullError);
                return "";
              } else if (!emailValidatorRegExp.hasMatch(value)) {
                addError(error: kInvalidEmailError);
                return "";
              }
              return null;
            },
            decoration: const InputDecoration(
              labelText: "Email",
              hintText: "Entrez votre adresse mail",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Mail.svg"),
            ),
          ),

          if (type_client == "2") ...[
            //Cas d'une entreprise
            const SizedBox(height: 10),
            TextFormField(
              textInputAction: TextInputAction.next,
              onSaved: (newValue) => rccm = newValue,
              decoration: const InputDecoration(
                labelText: "RCCM",
                hintText: "Entrez RCCM",
                // If  you are using latest version of flutter then lable text and hint text shown like this
                // if you r using flutter less then 1.20.* then maybe this is not working properly
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cart Icon.svg"),
              ),
            ),

            const SizedBox(height: 10),
            TextFormField(
              onSaved: (newValue) => ncc = newValue,
              decoration: const InputDecoration(
                labelText: "NCC",
                hintText: "Entrez NCC",
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cart Icon.svg"),
              ),
            ),
            const SizedBox(height: 15),
            // Upload DFE
            OutlinedButton.icon(
              onPressed: () async {
                FilePickerResult? result = await FilePicker.platform.pickFiles(
                  type: FileType.custom,
                  allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
                );
                if (result != null) {
                  setState(() {
                    dfeFile = File(result.files.single.path!);
                    dfeFileName = result.files.single.name;
                  });
                }
              },
              icon: const Icon(Icons.upload_file),
              label: Text(dfeFileName ?? "DFE (obligatoire) *"),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(double.infinity, 50),
                foregroundColor: dfeFile != null ? Colors.green : kPrimaryColor,
              ),
            ),
            const SizedBox(height: 10),
            // Upload Registre de commerce
            OutlinedButton.icon(
              onPressed: () async {
                FilePickerResult? result = await FilePicker.platform.pickFiles(
                  type: FileType.custom,
                  allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
                );
                if (result != null) {
                  setState(() {
                    rcFile = File(result.files.single.path!);
                    rcFileName = result.files.single.name;
                  });
                }
              },
              icon: const Icon(Icons.upload_file),
              label: Text(rcFileName ?? "Registre de commerce (obligatoire) *"),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(double.infinity, 50),
                foregroundColor: rcFile != null ? Colors.green : kPrimaryColor,
              ),
            ),
          ],

          const SizedBox(height: 10),
          TextFormField(
            obscureText: !_isPasswordVisible1,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => password = newValue,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kPassNullError);
              } else if (value.length >= 4) {
                removeError(error: kShortPassError);
              }
              password = value;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kPassNullError);
                return "";
              } else if (value.length < 4) {
                addError(error: kShortPassError);
                return "";
              }
              return null;
            },
            decoration: InputDecoration(
              labelText: "Mot de passe",
              hintText: "Entrez votre mot de passe",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: IconButton(
                  onPressed: () {
                    setState(() {
                      _isPasswordVisible1 =
                      !_isPasswordVisible1; // Inverse la visibilité
                    });
                  },
                  icon: Icon(
                    _isPasswordVisible1
                        ? Icons.visibility
                        : Icons.visibility_off,
                  )),
            ),
          ),

          const SizedBox(height: 10),
          TextFormField(
            obscureText: !_isPasswordVisible2,
            onSaved: (newValue) => confirm_password = newValue,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kPassNullError);
              } else if (value.isNotEmpty && password == confirm_password) {
                removeError(error: kMatchPassError);
              }
              confirm_password = value;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kPassNullError);
                return "";
              } else if ((password != value)) {
                addError(error: kMatchPassError);
                return "";
              }
              return null;
            },
            decoration: InputDecoration(
              labelText: "Confirmation mot de passe",
              hintText: "Confirmez votre mot de passe",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: IconButton(
                  onPressed: () {
                    setState(() {
                      _isPasswordVisible2 =
                      !_isPasswordVisible2; // Inverse la visibilité
                    });
                  },
                  icon: Icon(
                    _isPasswordVisible2
                        ? Icons.visibility
                        : Icons.visibility_off,
                  )),
            ),
          ),

          const SizedBox(height: 10),
          TextFormField(
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => codeParain = newValue,
            textCapitalization: TextCapitalization.characters,
            decoration: const InputDecoration(
              labelText: "Code parain (Optionnel)",
              hintText: "Entrez votre code parain",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Bell.svg"),
            ),
          ),


          FormError(errors: errors),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () async {
              if(pays_id <= 0 || ville_id <= 0){
                EasyLoading.showError("Veuillez choisir votre pays et votre ville");
              } else if (type_client == "2" && (dfeFile == null || rcFile == null)) {
                EasyLoading.showError("Veuillez uploader le DFE et le Registre de commerce");
              } else {
                if (_formKey.currentState!.validate()) {
                  _formKey.currentState!.save();
                  await signUpCtrl();
                }
              }
            },
            child: const Text("Je m'inscrit"),
          ),
        ],
      ),
    );
  }

  signUpCtrl() async {

    if (await verifierConnexion()) {
      try {

        afficherChargement();

        if (kDebugMode) {
          print('=== INSCRIPTION DEBUG ===');
          print('nom: $nom, email: $email, contact: $telephone');
          print('type_client: $type_client, pays_id: $pays_id, ville_id: $ville_id');
          print('URL: ${lienAPI()}inscription');
        }

        http.Response retourHttpLocal;

        if (type_client == "2" && dfeFile != null && rcFile != null) {
          // Multipart pour entreprise avec fichiers
          var request = http.MultipartRequest('POST', Uri.parse('${lienAPI()}inscription'));
          request.fields['nom_prenoms'] = nom ?? '';
          request.fields['code_parain'] = codeParain ?? '';
          request.fields['email'] = email ?? '';
          request.fields['contact'] = telephone ?? '';
          request.fields['password'] = password ?? '';
          request.fields['type_client'] = type_client;
          request.fields['rccm'] = rccm ?? '';
          request.fields['ncc'] = ncc ?? '';
          request.fields['pays_id'] = pays_id.toString();
          request.fields['ville_id'] = ville_id.toString();
          request.files.add(await http.MultipartFile.fromPath('dfe', dfeFile!.path));
          request.files.add(await http.MultipartFile.fromPath('registre_commerce', rcFile!.path));
          var streamedResponse = await request.send().timeout(const Duration(minutes: 2));
          retourHttpLocal = await http.Response.fromStream(streamedResponse);
        } else {
          var param = {
            'nom_prenoms': nom,
            'code_parain': codeParain,
            'email': email,
            'contact': telephone,
            'password': password,
            'type_client': type_client,
            'rccm': rccm,
            'ncc': ncc,
            'pays_id': pays_id,
            'ville_id': ville_id,
          };

          if (kDebugMode) {
            print(param);
          }

          retourHttpLocal = await http.post(Uri.parse('${lienAPI()}inscription'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
              .timeout(const Duration(minutes: 2));
        }

        retourHttp = retourHttpLocal;

          if (kDebugMode) {
            print('STATUS CODE: ${retourHttp.statusCode}');
            print('BODY: ${retourHttp.body}');
          }

        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {

          user = User.fromJson(datas);

          if (user.code == 200) {
            lireOuEcrireDonnee("token", user.token.toString(), 1);
            lireOuEcrireDonnee("type", user.type.toString(), 1);
            lireOuEcrireDonnee("nom", user.nom.toString(), 1);
            lireOuEcrireDonnee("photo", user.photo.toString(), 1);
            lireOuEcrireDonnee("code_parrain", user.code_parrain.toString(), 1);

            lireOuEcrireDonnee("tva", user.tva.toString(), 1);
            lireOuEcrireDonnee("devise", user.devise.toString(), 1);

            tva = user.tva ?? 0;
            devise = user.devise.toString();

            fermerChargement();
            await showDialog(
              context: context,
              barrierDismissible: false,
              builder: (ctx) => AlertDialog(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                icon: const Icon(Icons.check_circle, color: Colors.green, size: 60),
                title: const Text("Compte cree avec succes !"),
                content: const Text(
                  "Un email de validation a ete envoye a votre adresse. Veuillez saisir le code recu pour finaliser votre inscription.",
                  textAlign: TextAlign.center,
                ),
                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.of(ctx).pop();
                      Get.toNamed(OtpScreen.routeName, arguments: 1);
                    },
                    child: const Text("Continuer", style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          }else{
            EasyLoading.showError(user.message.toString());
          }
        }
      } catch (e) {
        EasyLoading.showError("Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }

      fermerChargement();

    }else{
      EasyLoading.showError("Veuillez vérifier votre connexion internet");
    }
  }
}
