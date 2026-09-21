import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:mon_gravier_com/models/adresse_de_livraison.dart';

import '../../globale.dart';
import 'package:http/http.dart' as http;

editerAdresse(UneAdresse adresse) async {

  if (await verifierConnexion()) {
    try {

      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "adresse": adresse.toJson(),
      };
      if (kDebugMode) {
        print(param);
      }
      
      if (kDebugMode) {
        print(adresse.toJson());
      }

      retourHttp = await http.post(Uri.parse('${lienAPI()}editer-adresse'),
          headers: {"Content-Type": "application/json"},
          body: jsonEncode(param))
          .timeout(const Duration(minutes: 2));
      

      var datas = jsonDecode(retourHttp.body);

      if (kDebugMode) {
        print(datas);
      }

      if (retourHttp.statusCode == 200) {
        if (datas["code"] == 200) {
          adresse = UneAdresse.fromJson(datas['data']);
        }else{
          afficherErreur(datas['message']);
        }
      }else{
        afficherErreur("Une erreur serveur s'est produite");
      }
    } catch (e) {
      afficherErreur(messageErreurTechnique(e));
      if (kDebugMode) {
        print(e.toString());
      }
    }

    fermerChargement();

    return adresse;
  }else{
    afficherInfo("Veuillez vérifier votre connexion internet");
  }
}