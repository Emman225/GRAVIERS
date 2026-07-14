import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:mon_gravier_com/models/User.dart';

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

      retourHttp = await http.post(Uri.parse('${lienAPI()}connexion'),
          headers: {"Content-Type": "application/json"},
          body: jsonEncode(param))
          .timeout(const Duration(minutes: 2));
      

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