import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
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
    'autre': 'Autre document (optionnel)',
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

  Future<void> _choisirDocument(String cle) async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
      withData: true, // récupère les octets pour l'envoi en base64
    );
    if (result != null && result.files.isNotEmpty) {
      final f = result.files.first;
      if (f.bytes == null) {
        EasyLoading.showError("Impossible de lire le fichier sélectionné");
        return;
      }
      if (f.size > 5 * 1024 * 1024) {
        EasyLoading.showError("Le document ne doit pas dépasser 5 Mo");
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
                Text(_docLibelles[cle]!,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
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
              icon: const Icon(Icons.close, color: Colors.red, size: 20),
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
              labelText: "Objet",
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
              labelText: "Description",
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
              "Elles accélèrent l'examen de votre demande.",
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
                EasyLoading.showError(msgErr);
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
