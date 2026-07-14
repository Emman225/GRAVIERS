class RetourListePaiementFilleule {
  int? code;
  String? message;
  List<PaiementFilleule>? data;

  RetourListePaiementFilleule({this.code, this.message, this.data});

  RetourListePaiementFilleule.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <PaiementFilleule>[];
      json['data'].forEach((v) {
        data!.add(new PaiementFilleule.fromJson(v));
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

class PaiementFilleule {
  int? id;
  int? clientId;
  int? devisId;
  String? code;
  String? libelle;
  double? montantTotal;
  double? montantRestant;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? serviceId;
  String? service;
  int? factureId;

  PaiementFilleule(
      {this.id,
      this.clientId,
      this.devisId,
      this.code,
      this.libelle,
      this.montantTotal,
      this.montantRestant,
      this.statut,
      this.deletedAt,
      this.createdAt,
      this.updatedAt,
      this.serviceId,
      this.service,
      this.factureId});

  PaiementFilleule.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    clientId = json['client_id'];
    devisId = json['devis_id'];
    code = json['code'];
    libelle = json['libelle'];
    montantTotal =
        json['montant_total'] != null ? double.parse(json['montant_total'].toString()) : 0;
    montantRestant = json['montant_restant'] != null
        ? double.parse(json['montant_restant'].toString())
        : 0;
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    serviceId = json['service_id'];
    service = json['service'];
    factureId = json['facture_id'];
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
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['service_id'] = this.serviceId;
    data['service'] = this.service;
    data['facture_id'] = this.factureId;
    return data;
  }
}
