class RetourListeDemandePaiement {
  int? code;
  String? message;
  List<DemandePaiement>? data;

  RetourListeDemandePaiement({this.code, this.message, this.data});

  RetourListeDemandePaiement.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <DemandePaiement>[];
      json['data'].forEach((v) {
        data!.add(new DemandePaiement.fromJson(v));
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

class DemandePaiement {
  int? id;
  int? montant;
  int? modePaiementId;
  int? userId;
  int? userValideId;
  String? dateValidation;
  bool? paye;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? modePaiement;
  String? numero_compte;
  String? date_demande;

  DemandePaiement(
      {this.id,
        this.montant,
        this.modePaiementId,
        this.userId,
        this.userValideId,
        this.dateValidation,
        this.paye,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.modePaiement,
        this.numero_compte,
        this.date_demande,
      });

  DemandePaiement.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    montant = json['montant'];
    modePaiementId = json['mode_paiement_id'];
    userId = json['user_id'];
    userValideId = json['user_valide_id'];
    dateValidation = json['date_validation'];
    paye = json['paye'] == 1 ? true : false;
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    modePaiement = json['mode_paiement'];
    numero_compte = json['numero_compte'];
    date_demande = json['date_demande'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['montant'] = this.montant;
    data['mode_paiement_id'] = this.modePaiementId;
    data['user_id'] = this.userId;
    data['user_valide_id'] = this.userValideId;
    data['date_validation'] = this.dateValidation;
    data['paye'] = this.paye;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['mode_paiement'] = this.modePaiement;
    data['numero_compte'] = this.numero_compte;
    data['date_demande'] = this.date_demande;
    return data;
  }
}
