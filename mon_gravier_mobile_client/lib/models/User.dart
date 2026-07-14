import 'package:mon_gravier_com/models/ConfigModel.dart';

class User {
  int? code = 0;
  String? token = "";
  String? photo = "";
  int? type = 0;
  ConfigModel? configs;
  String? data = "";
  String? message = "";
  String? nom = "";
  String? code_parrain = "";
  int? tva = 0;
  String? devise = "";
  bool? clientATerme = false;

  User(
      {this.code,
       this.token,
       this.photo,
       this.type,
       this.configs,
       this.data,
       this.message,
       this.nom,
       this.code_parrain,
       this.tva,
       this.devise,
       this.clientATerme,
      });

  User.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    token = json['token'];
    photo = json['photo'];
    type = json['type'];
    configs = json['code'] == 200 ? ConfigModel.fromJson(json['configs']) : null;
    data = json['data'];
    message = json['message'];
    nom = json['nom'];
    code_parrain = json['code_parrain'];
    tva = json['tva'];
    devise = json['devise'];
    clientATerme = json['cat'] == 1 ? true : false;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['code'] = code;
    data['token'] = token;
    data['photo'] = photo;
    data['type'] = type;
    data['configs'] = configs;
    data['data'] = this.data;
    data['message'] = message;
    data['nom'] = nom;
    data['code_parrain'] = code_parrain;
    data['tva'] = tva;
    data['devise'] = devise;
    data['cat'] = clientATerme;
    return data;
  }
}
