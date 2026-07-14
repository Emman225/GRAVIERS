import 'dart:convert';
import 'dart:io';

import 'package:camera_camera/camera_camera.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;

import '../../../components/custom_surfix_icon.dart';
import '../../../components/form_error.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../helper/constants.dart';
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
  String? numero_piece;
  String? password;
  String? confirm_password;
  bool remember = false;
  final List<String?> errors = [];
  bool _isPasswordVisible1 = false;
  bool _isPasswordVisible2 = false;
  ConfigModel? config = ConfigModel();
  List<Pays> pays = [];
  List<Villes> villesTot = [];
  List<Villes> villes = [];
  List<ModePaiements> modePaiements = [];
  TextEditingController paysController = TextEditingController();
  TextEditingController villeController = TextEditingController();
  int pays_id = 1, ville_id = 1;
  int? mode_paiement_id;
  File? _recto, _verso;

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
                              _recto = await rognerImage(context, file.path);
                            } else {
                              _verso = await rognerImage(context, file.path);
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
                                            _recto = await rognerImage(
                                                context, file.path);
                                          } else {
                                            _verso = await rognerImage(
                                                context, file.path);
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

  chargerConfig() async {
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
        retourHttp = await http.get(Uri.parse('${lienAPI()}get-config'),
            headers: {
              "Content-Type": "application/json"
            }).timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('get-config status: ${retourHttp.statusCode}');
          print(retourHttp.body);
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          setState(() {
            config = ConfigModel.fromJson(datas);
            pays = config?.pays ?? [];
            villesTot = config?.villes ?? [];
            modePaiements = config?.modePaiements ?? [];
          });
          if (kDebugMode) {
            print('Pays chargés: ${pays.length}');
            print('Villes chargées: ${villesTot.length}');
            print('Modes de paiement chargés: ${modePaiements.length}');
          }
        } else {
          EasyLoading.showError(
              "Erreur de chargement des données (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        EasyLoading.showError(
            "Impossible de charger la configuration. Vérifiez votre connexion.");
        if (kDebugMode) {
          print('Erreur get-config: ${e.toString()}');
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerConfig();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [
          DropdownButtonFormField<int>(
            value: pays.isNotEmpty ? pays_id : null,
            decoration: const InputDecoration(
              labelText: "Pays",
              hintText: "Choisir votre pays",
              floatingLabelBehavior: FloatingLabelBehavior.always,
            ),
            items: pays.map((p) {
              return DropdownMenuItem<int>(
                value: p.id,
                child: Text(p.nom ?? ''),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                pays_id = value ?? 1;
                villes = villesTot.where((v) => v.paysId == pays_id).toList();
                ville_id = villes.isNotEmpty ? (villes.first.id ?? 1) : 1;
              });
            },
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<int>(
            key: ValueKey('ville_$pays_id'),
            value: villes.any((v) => v.id == ville_id) ? ville_id : null,
            decoration: const InputDecoration(
              labelText: "Ville",
              hintText: "Choisir votre ville",
              floatingLabelBehavior: FloatingLabelBehavior.always,
            ),
            items: villes.map((v) {
              return DropdownMenuItem<int>(
                value: v.id,
                child: Text(v.nom ?? ''),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                ville_id = value ?? 1;
              });
            },
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
            decoration: const InputDecoration(
              labelText: "Nom & Prénoms ou Raison social",
              hintText: "Entrez votre nom & prénoms ou raison social",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/User.svg"),
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
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            onSaved: (newValue) => numero_piece = newValue,
            onChanged: (value) {
              if (value.isNotEmpty) {
                removeError(error: kCniNullError);
              }
              return;
            },
            validator: (value) {
              if (value!.isEmpty) {
                addError(error: kCniNullError);
                return "";
              }
              return null;
            },
            decoration: const InputDecoration(
              labelText: "CNI/Pièce",
              hintText: "Entrez votre numéro de CNI/Pièce",
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: Icon(Icons.perm_identity_outlined),
            ),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<int>(
            isExpanded: true,
            value: modePaiements.any((m) => m.id == mode_paiement_id)
                ? mode_paiement_id
                : null,
            decoration: const InputDecoration(
              labelText: "Mode de paiement préféré",
              hintText: "Choisir votre mode de paiement",
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: Icon(Icons.payment),
            ),
            items: modePaiements.map((m) {
              return DropdownMenuItem<int>(
                value: m.id,
                child: Text(
                  m.libelle ?? '',
                  overflow: TextOverflow.ellipsis,
                ),
              );
            }).toList(),
            onChanged: (value) {
              setState(() {
                mode_paiement_id = value;
                if (value != null) {
                  removeError(error: kModePaiementNullError);
                }
              });
            },
            validator: (value) {
              if (value == null) {
                addError(error: kModePaiementNullError);
                return "";
              }
              return null;
            },
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
          _imagesWidgets(context),
          FormError(errors: errors),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () async {
              if (pays_id <= 0 || ville_id <= 0) {
                EasyLoading.showError(
                    "Veuillez choisir votre pays et votre ville");
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

  _imagesWidgets(BuildContext context) {
    return Column(
      children: [
        const Divider(),
        const Text(
            "Cliquez sur l'image pour prendre la photo ou charger le fichier",
            textAlign: TextAlign.center),
        Row(
          mainAxisAlignment: mainCenter,
          crossAxisAlignment: crossCenter,
          children: [
            Column(
              children: [
                //*/ Pièce Recto /*//
                addVerticalSpace(2),
                (_recto?.lengthSync() == null)
                    ? IconButton(
                        iconSize: 70,
                        onPressed: () => openCamera(1),
                        icon: const Icon(
                          Icons.perm_identity_outlined,
                          color: kPrimaryColor,
                        ),
                      )
                    : GestureDetector(
                        child: Image.memory(
                          _recto?.readAsBytesSync() ?? Uint8List(12),
                          height: 70,
                          width: 70,
                        ),
                        onTap: () => openCamera(1)),
                const Text(
                  "Pièce recto",
                  textAlign: TextAlign.center,
                ),
                //*/ ------- /*//
              ],
            ),
            addHorizontalSpace(50),
            Column(
              children: [
                //*/ Pièce verso /*//
                addVerticalSpace(3),
                (_verso?.lengthSync() == null)
                    ? IconButton(
                        iconSize: 70,
                        onPressed: () => openCamera(2),
                        icon: const Icon(
                          Icons.list_rounded,
                          color: kPrimaryColor,
                        ),
                      )
                    : GestureDetector(
                        child: Image.memory(
                          _verso?.readAsBytesSync() ?? Uint8List(12),
                          height: 70,
                          width: 70,
                        ),
                        onTap: () => openCamera(2)),
                const Text(
                  "Pièce verso",
                  textAlign: TextAlign.center,
                ),
                //*/ ------- /*//
              ],
            ),
          ],
        ),
        const Divider(),
      ],
    );
  }

  signUpCtrl() async {
    if (await verifierConnexion()) {
      try {
        afficherChargement();

        Uint8List? bcByteRecto, bcByteVerso;
        if (_recto != null) {
          bcByteRecto = await _recto?.readAsBytes();
        }
        if (_verso != null) {
          bcByteVerso = await _verso?.readAsBytes();
        }

        var param = {
          'nom_prenoms': nom,
          'email': email,
          'contact': telephone,
          'password': password,
          'pays_id': pays_id,
          'ville_id': ville_id,
          'numero_piece': numero_piece,
          'mode_paiement_id': mode_paiement_id,
          'recto': bcByteRecto == null ? '' : base64Encode(bcByteRecto),
          'verso': bcByteVerso == null ? '' : base64Encode(bcByteVerso),
        };

        if (kDebugMode) {
          print(param);
        }

        retourHttp = await http
            .post(Uri.parse('${lienAPI()}inscription'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));

        if (kDebugMode) {
          print('inscription status: ${retourHttp.statusCode}');
          print(retourHttp.body);
        }

        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);

          user = User.fromJson(datas);

          if (user.code == 200) {
            lireOuEcrireDonnee("token", user.token.toString(), 1);
            lireOuEcrireDonnee("type", user.type.toString(), 1);
            lireOuEcrireDonnee("nom", user.nom.toString(), 1);
            lireOuEcrireDonnee("photo", user.photo.toString(), 1);
            Get.toNamed(OtpScreen.routeName, arguments: 1);
          } else {
            String msg = user.message.toString();
            if (msg.toLowerCase().contains('sql') ||
                msg.toLowerCase().contains('duplicate') ||
                msg.toLowerCase().contains('integrity')) {
              msg = "Cette adresse email est déjà utilisée.";
            }
            EasyLoading.showError(msg);
          }
        } else {
          EasyLoading.showError(
              "Erreur serveur. Veuillez réessayer plus tard.");
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }

      fermerChargement();
    } else {
      EasyLoading.showError("Veuillez vérifier votre connexion internet");
    }
  }
}
