import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';

import 'User.dart';

class RetourHome {
  int? code;
  String? message;
  DataRetourHome? data;

  RetourHome({this.code, this.message, this.data});

  RetourHome.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new DataRetourHome.fromJson(json['data']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['code'] = this.code;
    data['message'] = this.message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class DataRetourHome {
  Livreur? livreur;
  List<Stats>? stats;
  List<DemandePaiement>? demandePaiement;

  DataRetourHome({this.livreur, this.stats, this.demandePaiement});

  DataRetourHome.fromJson(Map<String, dynamic> json) {
    livreur =
    json['livreur'] != null ? new Livreur.fromJson(json['livreur']) : null;
    if (json['stats'] != null) {
      stats = <Stats>[];
      json['stats'].forEach((v) {
        stats!.add(new Stats.fromJson(v));
      });
    }
    if (json['paiements'] != null) {
      demandePaiement = <DemandePaiement>[];
      json['paiements'].forEach((v) {
        demandePaiement!.add(new DemandePaiement.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.livreur != null) {
      data['livreur'] = this.livreur!.toJson();
    }
    if (this.stats != null) {
      data['stats'] = this.stats!.map((v) => v.toJson()).toList();
    }
    if (this.demandePaiement != null) {
      data['paiements'] =
          this.demandePaiement!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Stats {
  int? attente;
  int? livree;

  Stats({this.attente, this.livree});

  Stats.fromJson(Map<String, dynamic> json) {
    attente = json['attente'];
    livree = json['livree'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['attente'] = this.attente;
    data['livree'] = this.livree;
    return data;
  }
}