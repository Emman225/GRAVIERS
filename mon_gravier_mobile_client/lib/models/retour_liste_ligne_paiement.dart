class RetourListeLignePaiement {
  int? code;
  String? message;
  List<LignePaiement>? data;

  RetourListeLignePaiement({this.code, this.message, this.data});

  RetourListeLignePaiement.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <LignePaiement>[];
      json['data'].forEach((v) {
        data!.add(new LignePaiement.fromJson(v));
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

class LignePaiement {
  int? id;
  int? paiementId;
  int? modePaiementId;
  String? reference;
  String? moyenPaiement;
  String? datePaiement;
  int? montant;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? userId;
  String? codePaiement;
  int? serviceId;
  String? service;
  String? libelle;
  String? nom;
  String? email;
  String? contact1;
  String? adresse;
  String? pays;
  String? ville;
  String? gestionnaire;

  LignePaiement(
      {this.id,
        this.paiementId,
        this.modePaiementId,
        this.reference,
        this.moyenPaiement,
        this.datePaiement,
        this.montant,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.userId,
        this.codePaiement,
        this.serviceId,
        this.service,
        this.nom,
        this.email,
        this.contact1,
        this.libelle,
        this.adresse,
        this.pays,
        this.ville,
        this.gestionnaire,
      });

  LignePaiement.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    paiementId = json['paiement_id'];
    modePaiementId = json['mode_paiement_id'];
    reference = json['reference'];
    moyenPaiement = json['moyen_paiement'];
    datePaiement = json['date_paiement'];
    montant = json['montant'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    userId = json['user_id'];
    codePaiement = json['code_paiement'];
    serviceId = json['service_id'];
    service = json['service'];
    libelle = json['libelle'];
    nom = json['nom'];
    email = json['email'];
    contact1 = json['contact1'];
    adresse = json['adresse'];
    pays = json['pays'];
    ville = json['ville'];
    gestionnaire = json['gestionnaire'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['paiement_id'] = this.paiementId;
    data['mode_paiement_id'] = this.modePaiementId;
    data['reference'] = this.reference;
    data['moyen_paiement'] = this.moyenPaiement;
    data['date_paiement'] = this.datePaiement;
    data['montant'] = this.montant;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['user_id'] = this.userId;
    data['code_paiement'] = this.codePaiement;
    data['service_id'] = this.serviceId;
    data['service'] = this.service;
    data['libelle'] = this.libelle;
    data['nom'] = this.nom;
    data['email'] = this.email;
    data['contact1'] = this.contact1;
    data['adresse'] = this.adresse;
    data['pays'] = this.pays;
    data['ville'] = this.ville;
    data['gestionnaire'] = this.gestionnaire;
    return data;
  }
}