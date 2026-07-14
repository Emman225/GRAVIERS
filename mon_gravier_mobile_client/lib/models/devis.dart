class RetourListeDevis {
  int? code;
  String? message;
  List<DataDevis>? data;

  RetourListeDevis({this.code, this.message, this.data});

  RetourListeDevis.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <DataDevis>[];
      json['data'].forEach((v) {
        data!.add(DataDevis.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['code'] = this.code;
    data['message'] = this.message;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class DataDevis {
  int? id;
  String? numero;
  int? clientId;
  double? montant;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? libelle;
  String? dateDevis;
  double? tva;
  double? cout_livraison;
  double? cout_reduction;
  double? montant_ht;
  int? mode_paiement;
  int? mode_paiement_id;
  int? type_livraison_id;
  String? adresse_livraison;
  int? adresse_livraison_id;
  String? date_Livraison;
  String? service;

  DataDevis(
      {this.id,
        this.numero,
        this.clientId,
        this.montant,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.libelle,
        this.dateDevis,
        this.tva,
        this.cout_livraison,
        this.cout_reduction,
        this.montant_ht,
        this.mode_paiement,
        this.mode_paiement_id,
        this.type_livraison_id,
        this.adresse_livraison,
        this.adresse_livraison_id,
        this.date_Livraison,
        this.service,
      });

  DataDevis.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    clientId = json['client_id'];
    montant = json['montant'] == null ? 0 : double.parse(json['montant'].toString());
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    libelle = json['libelle'];
    dateDevis = json['date_devis'];
    tva = json['tva'] == null ? 0 : double.parse(json['tva'].toString());
    cout_livraison = json['cout_livraison'] == null ? 0 : double.parse(json['cout_livraison'].toString());
    cout_reduction = json['cout_reduction'] == null ? 0 : double.parse(json['cout_reduction'].toString());
    montant_ht = json['montant_ht'] == null ? 0 : double.parse(json['montant_ht'].toString());
    mode_paiement = json['mode_paiement'];
    mode_paiement_id = json['mode_paiement_id'];
    type_livraison_id = json['type_livraison_id'];
    adresse_livraison = json['adresse_livraison'];
    adresse_livraison_id = json['adresse_livraison_id'];
    date_Livraison = json['date_Livraison'];
    service = json['service']?.toString();
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['client_id'] = this.clientId;
    data['montant'] = this.montant;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['libelle'] = this.libelle;
    data['date_devis'] = this.dateDevis;
    data['tva'] = this.tva;
    data['cout_livraison'] = this.cout_livraison;
    data['cout_reduction'] = this.cout_reduction;
    data['montant_ht'] = this.montant_ht;
    data['mode_paiement'] = this.mode_paiement;
    data['mode_paiement_id'] = this.mode_paiement_id;
    data['type_livraison_id'] = this.type_livraison_id;
    data['adresse_livraison'] = this.adresse_livraison;
    data['adresse_livraison_id'] = this.adresse_livraison_id;
    data['date_Livraison'] = this.date_Livraison;
    data['service'] = this.service;
    return data;
  }
}

