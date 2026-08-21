import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:file_picker/file_picker.dart';
import 'package:camera_camera/camera_camera.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../../components/custom_surfix_icon.dart';
import '../../../constants.dart';
import '../../../globale.dart';
import '../../../helper/constants.dart';
import '../../../models/ConfigModel.dart';
import '../../../models/InformationUtilisateur.dart';

class EditProfileForm extends StatefulWidget {
  const EditProfileForm({super.key});

  @override
  _EditProfileFormState createState() => _EditProfileFormState();
}

class _EditProfileFormState extends State<EditProfileForm> {
  final _formKey = GlobalKey<FormState>();
  TextEditingController nomController = TextEditingController();
  TextEditingController telephoneController = TextEditingController();
  TextEditingController paysController = TextEditingController();
  TextEditingController villeController = TextEditingController();
  TextEditingController adresseController = TextEditingController();
  int pays_id = 1, ville_id = 1;
  List<Pays> pays = [];
  List<Ville> villesTot = [];
  List<Ville> villes = [];
  File? _photoIdentite;

  String urlPhoto = '';
  InformationUtilisateur leUser = InformationUtilisateur();

  openCamera() {
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
                            _photoIdentite =
                                await rognerImage(context, file.path);
                            // Choix du fichier puis recadrage : l'écran peut
                            // avoir été quitté entre-temps. La photo reste
                            // retenue ; seul l'affichage est conditionné.
                            if (!mounted) return;
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
                                          _photoIdentite = await rognerImage(
                                              context, file.path);
                                          // Même précaution après la prise de
                                          // vue et le recadrage.
                                          if (!mounted) return;
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

  getUserInfos() async {
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
            .post(Uri.parse('${lienAPI()}infos-utilisateur'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          leUser = InformationUtilisateur.fromJson(datas);
          if (leUser.code == 200) {
            // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
            // et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              nomController.text = leUser.data?.nomPrenoms.toString() ?? '';
              telephoneController.text = leUser.data?.contact.toString() ?? '';
              adresseController.text = leUser.data?.adresse ?? '';
              urlPhoto = leUser.data?.photo.toString() ?? '';
              pays_id = leUser.data?.paysId ?? 0;
              if (pays_id > 0) {
                villes = villesTot.where((v) => v.paysId == pays_id).toList();
                ville_id = leUser.data?.villeId ?? 1;
                var p = pays.firstWhere((p) => p.id == pays_id);
                var v = villes.firstWhere((v) => v.id == ville_id);
                paysController.text = p.nom.toString();
                villeController.text = v.nom.toString();
              }
            });
          } else {
            afficherErreur(leUser.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    pays = user.configs?.pays ?? [];
    villesTot = user.configs?.villes ?? [];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      getUserInfos();
    });
  }

  @override
  void dispose() {
    nomController.dispose();
    telephoneController.dispose();
    paysController.dispose();
    villeController.dispose();
    adresseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [
          const SizedBox(height: 20),
          _profilePictureWidget(),
          TextFormField(
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            controller: nomController,
            textCapitalization: TextCapitalization.characters,
            decoration: const InputDecoration(
              labelText: "Nom & Prénoms",
              hintText: "Entrez votre nom & prénoms",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/User.svg"),
            ),
          ),
          const SizedBox(height: 20),
          TextFormField(
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.next,
            controller: telephoneController,
            decoration: const InputDecoration(
              labelText: "Téléphone",
              hintText: "Entrez votre téléphone",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Phone.svg"),
            ),
          ),
          const SizedBox(height: 20),
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
          const SizedBox(height: 20),
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
          const SizedBox(height: 20),
          TextFormField(
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.done,
            controller: adresseController,
            decoration: const InputDecoration(
              labelText: "Adresse",
              hintText: "Entrez votre adresse",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon:
                  CustomSurffixIcon(svgIcon: "assets/icons/Location point.svg"),
            ),
          ),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () {
              if (_formKey.currentState!.validate()) {
                _formKey.currentState!.save();
                editProfileCtrl();
              }
            },
            child: const Text("Modifier mes informations"),
          ),
        ],
      ),
    );
  }

  _profilePictureWidget() {
    return GestureDetector(
      onTap: () => openCamera(),
      child: Container(
        width: 100,
        height: 100,
        margin: const EdgeInsets.symmetric(vertical: 30 * 0.5),
        decoration: BoxDecoration(
          color: whiteColor,
          image: (_photoIdentite?.lengthSync() == null)
              ? (urlPhoto == 'null' || urlPhoto == '')
                  ? const DecorationImage(
                      image: AssetImage('assets/images/user.png'),
                      fit: BoxFit.fitHeight)
                  : DecorationImage(
                      image: NetworkImage(urlPhoto), fit: BoxFit.fitHeight)
              : DecorationImage(
                  image: Image.memory(
                    _photoIdentite?.readAsBytesSync() ?? Uint8List(12),
                    height: 100,
                    width: 100,
                  ).image,
                  fit: BoxFit.fitHeight),
          shape: BoxShape.circle,
        ),
      ),
    );
  }

  editProfileCtrl() async {
    if (_validationSaisie()) {
      if (await verifierConnexion()) {
        try {
          afficherChargement();

          Uint8List? photoIdentiteByte;
          if (_photoIdentite != null) {
            photoIdentiteByte = await _photoIdentite?.readAsBytes();
          }

          var param = {
            'access': user.token,
            'type': user.type,
            'nom_prenoms': nomController.text.trim(),
            'contact': telephoneController.text.trim(),
            'pays_id': pays_id,
            'ville_id': ville_id,
            'adresse': adresseController.text.trim(),
            'photo': photoIdentiteByte == null ? null : base64Encode(photoIdentiteByte),
          };

          if (kDebugMode) {
            print(param);
          }

          retourHttp = await http
              .post(Uri.parse('${lienAPI()}edit-profil'),
                  headers: {"Content-Type": "application/json"},
                  body: jsonEncode(param))
              .timeout(const Duration(minutes: 2));

          var datas = jsonDecode(retourHttp.body);
          if (retourHttp.statusCode == 200) {
            if (kDebugMode) {
              print(datas);
            }

            leUser = InformationUtilisateur.fromJson(datas);

            if (leUser.code == 200) {
              // Ici, contrairement aux autres écrans, les valeurs sont GLOBALES
              // et enregistrées sur l'appareil : les sauter parce que l'écran a
              // été quitté ferait perdre la modification du profil que le
              // serveur vient pourtant d'accepter. Seul l'affichage est
              // conditionné à la présence de l'écran.
              user.nom = leUser.data?.nomPrenoms.toString() ?? '';
              user.photo = leUser.data?.photo.toString() ?? '';
              urlPhoto = user.photo ?? '';
              lireOuEcrireDonnee("nom", user.nom ?? '', 1);
              lireOuEcrireDonnee("photo", user.photo ?? '', 1);
              if (mounted) setState(() {});
              afficherSucces(leUser.message.toString());
            } else {
              afficherErreur(leUser.message.toString());
            }
          } else {
            // Sans cette branche, une réponse serveur en erreur ne produisait
            // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
            afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
          }
        } catch (e) {
          afficherErreur(messageErreurTechnique(e));
          ;
          if (kDebugMode) {
            print(e.toString());
          }
        }

        fermerChargement();
      } else {
        afficherErreur("Veuillez vérifier votre connexion internet");
      }
    } else {
      afficherErreur(msgErr);
    }
  }

  _validationSaisie() {
    bool pass = true;
    if (pays_id <= 0) {
      pass = false;
      msgErr = "Veuillez choisir un pays valide";
    }
    if (ville_id <= 0) {
      pass = false;
      msgErr = "Veuillez choisir une ville valide";
    }
    if (adresseController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner une adresse de livraison valide";
    }
    if (nomController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner un nom et prénoms valide";
    }
    if (telephoneController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez renseigner un téléphone valide";
    }
    return pass;
  }
}
