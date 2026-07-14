import 'package:flutter/foundation.dart';

class RetourUnNewPaiement {
  int? code;
  String? message;
  List<UnNewPaiement>? data;

  RetourUnNewPaiement({this.code, this.message, this.data});

  RetourUnNewPaiement.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <UnNewPaiement>[];
      json['data'].forEach((v) {
        data!.add(new UnNewPaiement.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['code'] = this.code;
    data['message'] = this.message;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class UnNewPaiement {
  int? id;
  int? clientId;
  int? devisId;
  String? code;
  String? libelle;
  double? montantTotal;
  double? montantRestant;
  int? statut;
  int? serviceId;
  String? service;
  int? factureId;
  String? datePaiement;

  UnNewPaiement(
      {this.id,
        this.clientId,
        this.devisId,
        this.code,
        this.libelle,
        this.montantTotal,
        this.montantRestant,
        this.statut,
        this.serviceId,
        this.service,
        this.factureId,
        this.datePaiement});

  UnNewPaiement.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    clientId = json['client_id'];
    devisId = json['devis_id'];
    code = json['code'].toString();
    libelle = json['libelle'];
    if (kDebugMode) {
      print("----------->");
      print(json);
    }
    montantTotal = double.parse(json['montant_total'].toString());
    montantRestant = double.parse(json['montant_restant'].toString());
    statut = json['statut'];
    serviceId = json['service_id'];
    service = json['service'];
    factureId = json['facture_id'];
    datePaiement = json['date_paiement'];
    if (kDebugMode) {
      print("----------->");
      print(json);
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['client_id'] = this.clientId;
    data['devis_id'] = this.devisId;
    data['code'] = this.code;
    data['libelle'] = this.libelle;
    data['montant_total'] = this.montantTotal;
    data['montant_restant'] = this.montantRestant;
    data['statut'] = this.statut;
    data['service_id'] = this.serviceId;
    data['service'] = this.service;
    data['facture_id'] = this.factureId;
    data['date_paiement'] = this.datePaiement;
    return data;
  }
}
