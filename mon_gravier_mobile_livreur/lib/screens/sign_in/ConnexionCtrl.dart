import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:mon_gravier_com_livreur/models/User.dart';

import '../../globale.dart';
import 'package:http/http.dart' as http;

signInCtrl(login, pass) async {

  if (await verifierConnexion()) {
    try {

      afficherChargement();

      var param = {
        "login":login,
        "password":pass
      };
      
      if (kDebugMode) {
        print(param);
      }

      String urlComplete = '${lienAPI()}connexion';
      if (kDebugMode) {
        print("Appel API: $urlComplete");
      }

      retourHttp = await http.post(Uri.parse(urlComplete),
          headers: {"Content-Type": "application/json"},
          body: jsonEncode(param))
          .timeout(const Duration(seconds: 30));
      

      if (kDebugMode) {
        print("Status: ${retourHttp.statusCode}");
        print("Content-Type: ${retourHttp.headers['content-type']}");
        print("Body: ${retourHttp.body.substring(0, retourHttp.body.length > 300 ? 300 : retourHttp.body.length)}");
      }

      if (retourHttp.headers['content-type']?.contains('application/json') != true) {
        user.code = 500;
        user.message = "Le serveur ne répond pas correctement. Veuillez réessayer plus tard.";
        if (kDebugMode) {
          print("Réponse non-JSON reçue (status ${retourHttp.statusCode}): ${retourHttp.body.substring(0, retourHttp.body.length > 200 ? 200 : retourHttp.body.length)}");
        }
        fermerChargement();
        return;
      }

      var datas = jsonDecode(retourHttp.body);
      if (retourHttp.statusCode == 200) {
        if (kDebugMode) {
          print(datas);
        }
        user = User.fromJson(datas);
      }
    } catch (e) {
      user.code = 500;
      user.message = "Une erreur s'est produite veuillez reesayer plus tard";
      if (kDebugMode) {
        print(e.toString());
      }
    }

    fermerChargement();

  }else{
    user.code = 500;
    user.message = "Veuillez vérifier votre connexion internet";
  }
}