class ListePaiement {
  int? code;
  String? message;
  List<UnPaiement>? data;

  ListePaiement({this.code, this.message, this.data});

  ListePaiement.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <UnPaiement>[];
      json['data'].forEach((v) {
        data!.add(new UnPaiement.fromJson(v));
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

class UnPaiement {
  int? id;
  String? numero;
  int? userId;
  double? montant;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? serviceId;
  String? service;
  int? clientId;
  String? datePaiement;
  // « Mes paiements » du site (lot 89, 16/09/2026) : le règlement dont on ouvre
  // le reçu, son numéro, son mode, son état, et pour un paiement en attente le
  // total à payer (montant = reste).
  int? paiementId;
  String? numCommande;
  String? numeroRecu;
  String? codePaiement;
  String? modePaiement;
  String? etat;
  double? montantAPayer;
  String? dateCommande;

  UnPaiement(
      {this.id,
        this.numero,
        this.userId,
        this.montant,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.serviceId,
        this.service,
        this.clientId,
        this.datePaiement});

  UnPaiement.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    userId = json['user_id'];
    montant = json['montant'] == null ? 0 : double.parse(json['montant'].toString());
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    serviceId = json['service_id'];
    service = json['service'];
    clientId = json['client_id'];
    datePaiement = json['date_paiement'];
    paiementId = json['paiement_id'] == null ? null : int.tryParse(json['paiement_id'].toString());
    numCommande = json['num_commande']?.toString();
    numeroRecu = json['numero_recu']?.toString();
    codePaiement = json['code_paiement']?.toString();
    modePaiement = json['mode_paiement']?.toString();
    etat = json['etat']?.toString();
    montantAPayer = json['montant_a_payer'] == null ? null : double.tryParse(json['montant_a_payer'].toString());
    dateCommande = json['date_commande']?.toString();
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['user_id'] = this.userId;
    data['montant'] = this.montant;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['service_id'] = this.serviceId;
    data['service'] = this.service;
    data['client_id'] = this.clientId;
    data['date_paiement'] = this.datePaiement;
    return data;
  }
}
