import 'dart:convert';
import 'dart:io';

import 'package:camera_camera/camera_camera.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../../constants.dart';
import '../../../globale.dart';

class DemandeClientATermeForm extends StatefulWidget {
  const DemandeClientATermeForm({super.key});

  @override
  DemandeClientATermeFormState createState() => DemandeClientATermeFormState();
}

class DemandeClientATermeFormState extends State<DemandeClientATermeForm> {
  final _formKey = GlobalKey<FormState>();
  TextEditingController objetController = TextEditingController();
  TextEditingController descController = TextEditingController();

  // Documents justificatifs (mêmes clés que le formulaire web
  // /demande-de-client-a-terme : rccm / bilan / piece_id / autre).
  static const Map<String, String> _docLibelles = {
    'rccm': 'RCCM / Registre de commerce',
    'bilan': 'Attestation de revenus / bilan',
    'piece_id': "Pièce d'identité du dirigeant / responsable",
    'autre': 'Autre document (facultatif)',
  };
  final Map<String, PlatformFile?> _docs = {
    'rccm': null,
    'bilan': null,
    'piece_id': null,
    'autre': null,
  };

  @override
  void initState() {
    objetController = TextEditingController();
    descController = TextEditingController();
    super.initState();
  }

  @override
  void dispose() {
    objetController.dispose();
    descController.dispose();
    super.dispose();
  }

  /// CHOISIR UN DOCUMENT : DEPUIS LE TELEPHONE, OU EN LE PHOTOGRAPHIANT.
  ///
  /// Les quatre pieces n'ouvraient que le selecteur de fichiers. Un client qui
  /// a le document en papier — et c'est le cas courant pour un RCCM ou un bilan
  /// — devait le photographier a part, retrouver le cliche, puis revenir.
  ///
  /// Meme fenetre que sur l'inscription et que dans l'application apporteur :
  /// deux choix, meme disposition. Aucune dependance nouvelle.
  Future<void> _choisirDocument(String cle) {
    return showDialog(
      barrierDismissible: true,
      context: context,
      builder: (BuildContext contexteFenetre) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(30),
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
                      onPressed: () {
                        Navigator.of(contexteFenetre).pop();
                        _depuisLesFichiers(cle);
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
                      onPressed: () {
                        Navigator.of(contexteFenetre).pop();
                        _depuisAppareilPhoto(cle);
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

  /// LA PHOTO DEVIENT UN DOCUMENT COMME LES AUTRES.
  ///
  /// Cet ecran n'envoie pas des chemins mais des OCTETS, encodes en base64 :
  /// une photo doit donc etre relue depuis le disque et emballee dans un
  /// `PlatformFile`, comme le ferait le selecteur de fichiers. Le meme controle
  /// de taille s'applique — 5 Mo au plus — sans quoi la voie appareil photo
  /// contournerait un garde-fou qui existe.
  Future<void> _depuisAppareilPhoto(String cle) async {
    final NavigatorState ecran = Navigator.of(context);

    await ecran.push(
      MaterialPageRoute(
        builder: (_) => CameraCamera(
          onFile: (fichier) async {
            final File rogne = await rognerImage(context, fichier.path);
            final octets = await rogne.readAsBytes();

            if (!mounted) return;

            if (octets.length > 5 * 1024 * 1024) {
              afficherErreur("Le document ne doit pas dépasser 5 Mo");
              ecran.pop();
              return;
            }

            setState(() {
              _docs[cle] = PlatformFile(
                name: '$cle-photo.jpg',
                size: octets.length,
                bytes: octets,
                path: rogne.path,
              );
            });

            ecran.pop();
          },
        ),
      ),
    );
  }

  Future<void> _depuisLesFichiers(String cle) async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
      withData: true, // récupère les octets pour l'envoi en base64
    );
    if (result != null && result.files.isNotEmpty) {
      final f = result.files.first;
      if (f.bytes == null) {
        afficherErreur("Impossible de lire le fichier sélectionné");
        return;
      }
      if (f.size > 5 * 1024 * 1024) {
        afficherErreur("Le document ne doit pas dépasser 5 Mo");
        return;
      }
      setState(() {
        _docs[cle] = f;
      });
    }
  }

  Widget _ligneDocument(String cle) {
    final fichier = _docs[cle];
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Astérisque sur les pièces obligatoires (16/09/2026), comme
                // sur les autres champs du formulaire.
                Text.rich(
                  TextSpan(
                    text: _docLibelles[cle]!,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.black87),
                    children: [
                      if (cle != 'autre')
                        const TextSpan(text: ' *', style: TextStyle(color: Colors.red, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
                Text(
                  fichier == null ? 'Aucun fichier choisi' : fichier.name,
                  style: TextStyle(
                      fontSize: 12,
                      color: fichier == null ? Colors.grey : Colors.green,
                      overflow: TextOverflow.ellipsis),
                ),
              ],
            ),
          ),
          if (fichier != null)
            IconButton(
              icon: const Icon(Icons.close, color: kErrorColor, size: 20),
              onPressed: () => setState(() => _docs[cle] = null),
            ),
          OutlinedButton.icon(
            onPressed: () => _choisirDocument(cle),
            icon: const Icon(Icons.attach_file, size: 16),
            label: Text(fichier == null ? 'Choisir' : 'Changer',
                style: const TextStyle(fontSize: 12)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Form(
      key: _formKey,
      child: Column(
        children: [
          TextFormField(
            controller: objetController,
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            maxLength: 50,
            decoration: const InputDecoration(
              labelText: "Objet *",
              hintText: "Saisir l'objet",
            ),
          ),
          const SizedBox(height: 20),
          TextFormField(
            maxLines: 5,
            controller: descController,
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.done,
            maxLength: 300,
            decoration: const InputDecoration(
              labelText: "Description *",
              hintText: "Description de la demande",
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: Text("Documents justificatifs",
                style: TextStyle(
                    fontSize: 15, fontWeight: FontWeight.bold, color: kPrimaryColor)),
          ),
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              "Joignez vos pièces (PDF, image ou Word — 5 Mo max par document). "
              "Les pièces marquées d'un astérisque (*) sont obligatoires.",
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ),
          const SizedBox(height: 8),
          ..._docLibelles.keys.map(_ligneDocument),
          const SizedBox(height: 20),
          ElevatedButton(
            onPressed: () {
              if (_validationSaisie()) {
                _envoyerDemande();
              }else{
                afficherErreur(msgErr);
              }
            },
            child: const Text("Envoyer ma demande"),
          ),
        ],
      ),
    );
  }

  _validationSaisie() {
    bool pass = true;
    if (objetController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir l'objet";
    }
    if (descController.text.trim() == '') {
      pass = false;
      msgErr = "Veuillez saisir la description de votre demande";
    }
    // Les trois premières pièces sont obligatoires (07/09/2026), comme sur
    // le site. Le message nomme ce qui manque.
    final manquantes = <String>[];
    for (final cle in ['rccm', 'bilan', 'piece_id']) {
      final f = _docs[cle];
      if (f == null || f.bytes == null) {
        manquantes.add(_docLibelles[cle] ?? cle);
      }
    }
    if (manquantes.isNotEmpty) {
      pass = false;
      msgErr = "Pièce(s) manquante(s) : ${manquantes.join(', ')}";
    }
    return pass;
  }

  _envoyerDemande() async {
    if (await verifierConnexion()) {
      afficherChargement();

      // Documents sélectionnés -> base64 (clé -> {fichier, extension})
      Map<String, dynamic> documents = {};
      _docs.forEach((cle, fichier) {
        if (fichier != null && fichier.bytes != null) {
          documents[cle] = {
            "fichier": base64Encode(fichier.bytes!),
            "extension": (fichier.extension ?? 'pdf').toLowerCase(),
          };
        }
      });

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "objet": objetController.text.trim(),
        "description": descController.text.trim(),
        if (documents.isNotEmpty) "documents": documents,
      };

      if (kDebugMode) {
        print(param.keys);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}demande-client-a-terme'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              objetController.text = '';
              descController.text = '';
              _docs.updateAll((k, v) => null);
            });
            afficherSucces(datas['message']);
          }else{
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

}
