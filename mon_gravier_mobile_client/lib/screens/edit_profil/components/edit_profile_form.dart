import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
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
  // Entreprise (lot 100, 16/09/2026) : RCCM, NCC et régime d'imposition.
  TextEditingController rccmController = TextEditingController();
  TextEditingController nccController = TextEditingController();
  String? regimeImposition;
  String natureFne = 'B2B';
  static const Map<String, String> naturesFne = {
    'B2B': 'Entreprise privée',
    'B2G': 'Administration ou institution publique',
    'B2F': "Client établi à l'étranger",
  };
  static const Map<String, String> regimesImposition = {
    'RNI': "Réel normal d'imposition",
    'RSI': "Réel simplifié d'imposition",
    'RME': 'Régime des micro-entreprises',
    'RE': "Taxe d'État de l'Entreprenant (TEE)",
  };
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
              rccmController.text = leUser.data?.rccm ?? '';
              nccController.text = leUser.data?.ncc ?? '';
              final regime = (leUser.data?.regimeImposition ?? '').toUpperCase();
              regimeImposition = regimesImposition.containsKey(regime) ? regime : null;
              final nature = (leUser.data?.natureFne ?? '').toUpperCase();
              natureFne = naturesFne.containsKey(nature) ? nature : 'B2B';
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
    rccmController.dispose();
    nccController.dispose();
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
              labelText: "Nom & Prénoms *",
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
              labelText: "Téléphone *",
              hintText: "Entrez votre téléphone",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Phone.svg"),
            ),
          ),
          const SizedBox(height: 20),
          if (leUser.data?.estEntreprise ?? false) ...[
            // Le client entreprise complet pour la facture normalisée (lot 100).
            TextFormField(
              textInputAction: TextInputAction.next,
              controller: rccmController,
              decoration: const InputDecoration(
                labelText: "Registre de commerce (RCCM) *",
                hintText: "Entrez votre RCCM",
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cart Icon.svg"),
              ),
            ),
            const SizedBox(height: 20),
            TextFormField(
              textInputAction: TextInputAction.next,
              controller: nccController,
              decoration: const InputDecoration(
                labelText: "N° de compte contribuable (NCC) *",
                hintText: "Entrez votre NCC",
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cart Icon.svg"),
              ),
            ),
            const SizedBox(height: 20),
            DropdownButtonFormField<String>(
              initialValue: regimeImposition,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: "Régime d'imposition *",
                hintText: "Choisir le régime",
                floatingLabelBehavior: FloatingLabelBehavior.always,
              ),
              items: regimesImposition.entries
                  .map((r) => DropdownMenuItem(
                        value: r.key,
                        child: Text(r.value, overflow: TextOverflow.ellipsis),
                      ))
                  .toList(),
              onChanged: (String? valeur) {
                setState(() => regimeImposition = valeur);
              },
            ),
            const SizedBox(height: 20),
            DropdownButtonFormField<String>(
              initialValue: natureFne,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: "Nature de l'organisation *",
                floatingLabelBehavior: FloatingLabelBehavior.always,
              ),
              items: naturesFne.entries
                  .map((n) => DropdownMenuItem(
                        value: n.key,
                        child: Text(n.value, overflow: TextOverflow.ellipsis),
                      ))
                  .toList(),
              onChanged: (String? valeur) {
                setState(() => natureFne = valeur ?? 'B2B');
              },
            ),
            const SizedBox(height: 20),
          ],
          Padding(
            padding: const EdgeInsets.all(5.0),
            child: DropDownTextField(
              textEditingController: paysController,
              title: 'Pays *',
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
              title: 'Ville *',
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
              labelText: "Adresse *",
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
    if ((leUser.data?.estEntreprise ?? false) &&
        (nccController.text.trim().isEmpty || rccmController.text.trim().isEmpty || regimeImposition == null)) {
      afficherErreur(
          "Pour une entreprise, le RCCM, le NCC et le régime d'imposition sont obligatoires : ils figurent sur vos factures.");
    } else if (_validationSaisie()) {
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
            // Entreprise : RCCM, NCC, régime (lot 100) ; ignorés par l'API pour un particulier.
            if (leUser.data?.estEntreprise ?? false) ...{
              'rccm': rccmController.text.trim(),
              'ncc': nccController.text.trim(),
              'regime_imposition': regimeImposition ?? '',
              'nature_fne': natureFne,
            },
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
              // L'ANCIENNE PHOTO DOIT SORTIR DU CACHE.
              //
              // Flutter garde les images téléchargées dans un cache indexé PAR
              // ADRESSE. Le serveur renvoyant la même adresse pour la photo
              // remplacée, l'écran « Mon espace » ressortait l'ANCIENNE image
              // du cache — la modification était pourtant bien enregistrée, et
              // bien renvoyée. On retire donc l'ancienne entrée avant
              // d'inscrire la nouvelle adresse.
              final ancienneAdresse = user.photo?.toString().trim() ?? '';
              if (ancienneAdresse.isNotEmpty && ancienneAdresse != 'null') {
                await NetworkImage(ancienneAdresse).evict();
              }

              user.nom = leUser.data?.nomPrenoms.toString() ?? '';
              user.photo = leUser.data?.photo.toString() ?? '';
              urlPhoto = user.photo ?? '';
              lireOuEcrireDonnee("nom", user.nom ?? '', 1);
              lireOuEcrireDonnee("photo", user.photo ?? '', 1);

              // La nouvelle adresse peut être identique à l'ancienne : on la
              // retire aussi, sans quoi le premier affichage relirait encore
              // le cache.
              final nouvelleAdresse = user.photo?.toString().trim() ?? '';
              if (nouvelleAdresse.isNotEmpty && nouvelleAdresse != 'null') {
                await NetworkImage(nouvelleAdresse).evict();
              }

              // ET SURTOUT : on change l'ADRESSE d'affichage.
              //
              // Vider le cache de Flutter ne suffisait pas — c'est ce qui a
              // fait échouer les deux corrections précédentes. Le serveur
              // enregistre la photo au même chemin quoi qu'il arrive
              // (`imageUser/{id}.png`), si bien que rien, dans l'adresse, ne
              // distingue la nouvelle image de l'ancienne : ni le cache
              // d'images, ni un éventuel cache HTTP intermédiaire ne peuvent
              // savoir qu'il faut retélécharger.
              //
              // Le compteur ajoute `?v=N` à l'adresse : elle devient
              // différente, et l'image est réellement redemandée.
              versionPhotoProfil++;

              // ET SURTOUT, LE CHEMIN QUI NE PEUT PAS ÉCHOUER : on retient les
              // OCTETS de l'image choisie. C'est ce que la vignette affichera
              // — pas un téléchargement, pas une adresse, pas un cache.
              if (photoIdentiteByte != null) {
                photoProfilLocale = photoIdentiteByte;
              }

              // La vignette est abonnée à ce signal : elle se redessine seule,
              // sans dépendre de la reconstruction de « Mon espace ».
              profilModifie.value++;

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
