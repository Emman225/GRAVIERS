import 'dart:convert';
import 'dart:io';

import 'package:camera_camera/camera_camera.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
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

  /// Regime d'imposition de l'entreprise cliente.
  ///
  /// Il figure sur sa facture, et la DGI l'attend sur une facture entre
  /// entreprises. La colonne existait cote serveur et FneService la lisait
  /// depuis toujours ; faute de saisie, la mention sortait vide.
  String? regimeImposition;
  // La nature de l'organisation pour la DGI (lot 100, 16/09/2026).
  String natureFne = 'B2B';
  static const Map<String, String> naturesFne = {
    'B2B': 'Entreprise privée',
    'B2G': 'Administration ou institution publique',
    'B2F': "Client établi à l'étranger",
  };

  /// Valeur envoyée = code court ; texte affiché = intitulé complet. Les
  /// intitulés sont ceux du site (App\Support\RegimeImposition) : un client
  /// inscrit depuis le téléphone porte le même régime qu'un client du site.
  static const Map<String, String> regimesImposition = {
    'RNI': "Réel normal d'imposition",
    'RSI': "Réel simplifié d'imposition",
    'RME': 'Régime des micro-entreprises',
    'RE': "Taxe d'État de l'Entreprenant (TEE)",
  };
  bool remember = false;
  final List<String?> errors = [];
  bool _isPasswordVisible1 = false;
  bool _isPasswordVisible2 = false;
  List<Pays> pays = [];
  List<Ville> villesTot = [];
  List<Ville> villes = [];
  TextEditingController paysController = TextEditingController();

  // Les deux pieces s'affichent dans un champ en lecture seule : il lui faut
  // un controleur pour porter le nom du document retenu.
  final TextEditingController dfeController = TextEditingController();
  final TextEditingController rcController = TextEditingController();
  TextEditingController villeController = TextEditingController();
  int pays_id = 1, ville_id = 1;
  File? dfeFile;
  File? rcFile;
  String? dfeFileName;
  String? rcFileName;

  @override
  void dispose() {
    paysController.dispose();
    dfeController.dispose();
    rcController.dispose();
    villeController.dispose();
    super.dispose();
  }

  /// CHOISIR UN FICHIER : DEPUIS LE TELEPHONE, OU EN LE PHOTOGRAPHIANT.
  ///
  /// Les deux pieces — DFE et registre de commerce — n'ouvraient que le
  /// selecteur de fichiers. Un client qui a le document en papier devait donc
  /// le photographier a part, retrouver le cliche, puis revenir : trois gestes
  /// la ou l'application apporteur en propose un.
  ///
  /// Cette fenetre reprend celle de l'apporteur, a l'identique — memes deux
  /// choix, meme disposition — pour que les trois applications se ressemblent.
  /// Aucune dependance nouvelle : `camera_camera`, `file_picker` et le rognage
  /// sont deja dans le projet.
  Future<void> choisirPiece({required bool estLeDfe}) {
    return showDialog(
      barrierDismissible: true,
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(kRadiusXl),
          ),
          elevation: 5.0,
          content: SizedBox(
            height: 100,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Column(
                  children: [
                    const SizedBox(height: 2),
                    IconButton(
                      iconSize: 40,
                      tooltip: 'Choisir un fichier deja enregistre',
                      onPressed: () async {
                        Navigator.of(context).pop();

                        final FilePickerResult? resultat =
                            await FilePicker.platform.pickFiles(
                          type: FileType.custom,
                          allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
                          dialogTitle: 'Choisir le document',
                        );

                        final String? chemin = resultat?.files.single.path;
                        if (chemin == null) return;

                        setState(() {
                          if (estLeDfe) {
                            dfeFile = File(chemin);
                            dfeFileName = resultat!.files.single.name;
                            dfeController.text = dfeFileName!;
                          } else {
                            rcFile = File(chemin);
                            rcFileName = resultat!.files.single.name;
                            rcController.text = rcFileName!;
                          }
                        });
                      },
                      icon: const Icon(Icons.image, color: kPrimaryColor),
                    ),
                    const Text(
                      'Existante',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: kTextColor,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
                const SizedBox(width: 50),
                Column(
                  children: [
                    const SizedBox(height: 2),
                    IconButton(
                      iconSize: 40,
                      tooltip: 'Photographier le document',
                      onPressed: () async {
                        // LES DEUX NAVIGATEURS SONT CAPTURES AVANT L'ATTENTE.
                        //
                        // Celui de la FENETRE la referme, celui de l'ECRAN
                        // ferme l'appareil photo. Les reprendre apres l'await
                        // par `context` reviendrait a lire un contexte qui peut
                        // avoir disparu entre-temps — c'est le piege que
                        // l'analyse signale, et il produit une exception opaque
                        // si l'utilisateur quitte l'ecran pendant la prise.
                        final NavigatorState fenetre = Navigator.of(context);
                        final NavigatorState ecran = Navigator.of(this.context);
                        final BuildContext contexteEcran = this.context;

                        await Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => CameraCamera(
                              onFile: (fichier) async {
                                // Rogne comme ailleurs dans l'application : une
                                // photo prise a main levee porte toujours du
                                // decor autour du document.
                                final File rogne = await rognerImage(
                                    contexteEcran, fichier.path);

                                if (!mounted) return;

                                setState(() {
                                  if (estLeDfe) {
                                    dfeFile = rogne;
                                    dfeFileName = 'Photo du DFE';
                                    dfeController.text = dfeFileName!;
                                  } else {
                                    rcFile = rogne;
                                    rcFileName = 'Photo du registre';
                                    rcController.text = rcFileName!;
                                  }
                                });

                                ecran.pop();
                              },
                            ),
                          ),
                        );

                        if (!mounted) return;
                        fenetre.pop();
                      },
                      icon: const Icon(Icons.camera, color: kPrimaryColor),
                    ),
                    const Text(
                      'Nouvelle',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: kTextColor,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  /// LA DECORATION DES AUTRES CHAMPS DU FORMULAIRE, A L'IDENTIQUE.
  ///
  /// « Pays », « Ville », RCCM et NCC sont tous des `TextFormField` portant
  /// cette meme decoration : le theme fournit la bordure, la hauteur et les
  /// couleurs. Quatre champs y echappaient — le type de client, le regime
  /// d'imposition et les deux pieces — dessines a la main en `Container`
  /// arrondi a 30, d'une autre forme et d'une autre taille au milieu des
  /// autres.
  ///
  /// Le suffixe reprend la geometrie de `CustomSurffixIcon` — 20 de marge
  /// autour d'une icone de 16 — car c'est LUI qui fixe la hauteur du champ.
  /// Une icone posee sans cette marge donnerait un champ plus court, et
  /// l'alignement se verrait aussitot.
  InputDecoration _decorationCommune({
    required String libelle,
    required String invite,
    required IconData icone,
    Color? couleurIcone,
  }) {
    return InputDecoration(
      labelText: libelle,
      hintText: invite,
      floatingLabelBehavior: FloatingLabelBehavior.always,
      suffixIcon: Padding(
        padding: const EdgeInsets.all(20),
        child: Icon(icone, size: 16, color: couleurIcone ?? kPrimaryColor),
      ),
    );
  }

  /// Un champ de piece jointe, au MEME dessin que les champs de saisie.
  ///
  /// En lecture seule : le toucher ouvre la fenetre de choix. Le nom du
  /// document remplace le texte d'invite une fois la piece retenue, et la
  /// bordure passe au vert — le formulaire dit ainsi ce qui est fait sans
  /// changer de forme.
  Widget _champPiece({
    required String libelle,
    required TextEditingController controleur,
    required bool renseigne,
    required VoidCallback onTap,
  }) {
    return TextFormField(
      controller: controleur,
      readOnly: true,
      showCursor: false,
      keyboardType: TextInputType.none,
      onTap: onTap,
      decoration: _decorationCommune(
        libelle: libelle,
        invite: 'Toucher pour choisir le document',
        icone: renseigne ? Icons.check_circle : Icons.upload_file,
        couleurIcone: renseigne ? kSuccessColor : kPrimaryColor,
      ),
    );
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
          // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
          // et le rafraîchissement échoue (voir devis_screen.dart).
          if (mounted) setState(() {
            pays = user.configs?.pays ?? [];
            villesTot = user.configs?.villes ?? [];
          });
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
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

          DropdownButtonFormField<String>(
            // `value` est deprecie depuis Flutter 3.33 au profit de
            // `initialValue`. Le remplacement est sans consequence ici : la
            // valeur ne change QUE par le champ lui-meme, jamais de l'exterieur.
            initialValue: type_client,
            items: comboTypeClient,
            isExpanded: true,
            // L'icone de la liste est retiree : le suffixe de la decoration en
            // tient lieu, comme sur « Pays ». Deux icones se seraient
            // superposees a droite du champ.
            icon: const SizedBox.shrink(),
            decoration: _decorationCommune(
              libelle: 'Type de client',
              invite: 'Particulier ou Entreprise',
              icone: Icons.person_add_outlined,
            ),
            onChanged: (String? valeur) {
              setState(() => type_client = valeur!);
            },
          ),

          const SizedBox(height: 10),
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
          const SizedBox(height: 10),
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
              labelText: type_client == '1' ? "Nom & Prénoms *" : "Raison sociale *",
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
              labelText: "Téléphone *",
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
              labelText: "Email *",
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
              // Obligatoires pour une entreprise (lot 100, 16/09/2026) : la DGI
              // exige le NCC du client sur une facture entre entreprises.
              validator: (valeur) => (valeur == null || valeur.trim().isEmpty)
                  ? "Le RCCM est obligatoire pour une entreprise"
                  : null,
              decoration: const InputDecoration(
                labelText: "RCCM *",
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
              validator: (valeur) => (valeur == null || valeur.trim().isEmpty)
                  ? "Le NCC (numéro de compte contribuable) est obligatoire : il figure sur vos factures"
                  : null,
              decoration: const InputDecoration(
                labelText: "NCC *",
                hintText: "Entrez NCC",
                floatingLabelBehavior: FloatingLabelBehavior.always,
                suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cart Icon.svg"),
              ),
            ),
            const SizedBox(height: 10),
            // REGIME D'IMPOSITION — il figure sur la facture du client, et la
            // DGI l'attend sur une facture entre entreprises.
            DropdownButtonFormField<String>(
              initialValue: regimeImposition,
              isExpanded: true,
              icon: const SizedBox.shrink(),
              decoration: _decorationCommune(
                libelle: "Régime d'imposition *",
                invite: 'Choisir le régime',
                icone: Icons.account_balance_outlined,
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
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: natureFne,
              isExpanded: true,
              icon: const SizedBox.shrink(),
              decoration: _decorationCommune(
                libelle: "Nature de l'organisation *",
                invite: 'Entreprise, administration…',
                icone: Icons.domain_outlined,
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

            // LES DEUX PIECES REPRENNENT LE DESSIN DES AUTRES CHAMPS.
            //
            // Elles etaient dessinees en boutons a bord fin, larges et plats,
            // au milieu de champs arrondis : elles ne ressemblaient a rien
            // d'autre dans le formulaire. Ce sont desormais des champs de meme
            // forme, en lecture seule, dont le toucher ouvre le choix
            // « Existante / Nouvelle ».
            const SizedBox(height: 10),
            _champPiece(
              libelle: 'DFE *',
              controleur: dfeController,
              renseigne: dfeFile != null,
              onTap: () => choisirPiece(estLeDfe: true),
            ),
            const SizedBox(height: 10),
            _champPiece(
              libelle: 'Registre de commerce *',
              controleur: rcController,
              renseigne: rcFile != null,
              onTap: () => choisirPiece(estLeDfe: false),
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
              labelText: "Mot de passe *",
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
              labelText: "Confirmation mot de passe *",
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
                afficherErreur("Veuillez choisir votre pays et votre ville");
              } else if (type_client == "2" && (dfeFile == null || rcFile == null)) {
                afficherErreur("Veuillez uploader le DFE et le Registre de commerce");
              } else if (type_client == "2" &&
                  (regimeImposition == null || regimeImposition!.isEmpty)) {
                // Exige comme les deux pieces : le regime figure sur la facture
                // du client, et la DGI l'attend sur une facture entre
                // entreprises. Le laisser vide ferait sortir la mention vide.
                afficherErreur("Veuillez choisir le régime d'imposition");
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
          request.fields['regime_imposition'] = regimeImposition ?? '';
          request.fields['nature_fne'] = natureFne;
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
            'nature_fne': natureFne,
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

            // La fenêtre « Compte créé avec succès » a été retirée : elle
            // n'apportait rien qu'un clic de plus, et son message est désormais
            // affiché sur l'écran de saisie du code, là où il est utile.
            // On y va donc directement.
            Get.toNamed(OtpScreen.routeName, arguments: 1);
          }else{
            afficherErreur(user.message.toString());
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

    }else{
      afficherErreur("Veuillez vérifier votre connexion internet");
    }
  }
}
