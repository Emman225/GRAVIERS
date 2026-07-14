class RetourCodePromo {
  int? code;
  String? message;
  Reduction? data;

  RetourCodePromo({this.code, this.message, this.data});

  RetourCodePromo.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new Reduction.fromJson(json['data']) : null;
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

class Reduction {
  int? id;
  String? code;
  String? libelle;
  String? debut;
  String? fin;
  int? estUtilise;
  int? tauxReduction;
  int? clientId;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Reduction(
      {this.id,
        this.code,
        this.libelle,
        this.debut,
        this.fin,
        this.estUtilise,
        this.tauxReduction,
        this.clientId,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  Reduction.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    code = json['code'];
    libelle = json['libelle'];
    debut = json['debut'];
    fin = json['fin'];
    estUtilise = json['est_utilise'];
    tauxReduction = json['taux_reduction'];
    clientId = json['client_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['code'] = this.code;
    data['libelle'] = this.libelle;
    data['debut'] = this.debut;
    data['fin'] = this.fin;
    data['est_utilise'] = this.estUtilise;
    data['taux_reduction'] = this.tauxReduction;
    data['client_id'] = this.clientId;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

