class UneCommande {
  int? id;
  String? numero;
  int? devisId;
  int? clientId;
  int? modePaiementId;
  int? adresseLivraisonId;
  String? dateCommande;
  double? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  int? statut;
  String? note;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  double? remise;
  double? montant_tva;
  double? cout_livraison_client;
  int? typeLivraisonId;
  int? est_livrable;
  String? modePaiement;
  String? type_livraison;
  String? adresse;
  String? numero_bl;
  String? fichier_bl;

  UneCommande(
      {this.id,
        this.numero,
        this.devisId,
        this.clientId,
        this.modePaiementId,
        this.adresseLivraisonId,
        this.dateCommande,
        this.montantTotal,
        this.etatCommande,
        this.dateLivraison,
        this.dateFinLivraison,
        this.statut,
        this.note,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.remise,
        this.montant_tva,
        this.cout_livraison_client,
        this.typeLivraisonId,
        this.est_livrable,
        this.type_livraison,
        this.modePaiement,
        this.adresse,
        this.numero_bl,
        this.fichier_bl,
      });

  UneCommande.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    devisId = json['devis_id'];
    type_livraison = json['type_livraison'];
    clientId = json['client_id'];
    modePaiementId = json['mode_paiement_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    dateCommande = json['date_commande'];
    montantTotal = double.parse(json['montant_total']==null ? '0' : json['montant_total'].toString());
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    statut = json['statut'];
    note = json['note'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    remise = double.parse(json['remise']==null ? '0' : json['remise'].toString());
    montant_tva = double.parse(json['montant_tva']==null ? '0' : json['montant_tva'].toString());
    cout_livraison_client = double.parse(json['cout_livraison_client']==null ? '0' : json['cout_livraison_client'].toString());
    typeLivraisonId = json['type_livraison_id'];
    est_livrable = json['est_livrable'];
    modePaiement = json['mode_paiement'];
    adresse = json['adresse'];
    fichier_bl = json['fichier_bl'];
    numero_bl = json['numero_bl'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['devis_id'] = this.devisId;
    data['client_id'] = this.clientId;
    data['type_livraison'] = this.type_livraison;
    data['mode_paiement_id'] = this.modePaiementId;
    data['adresse_livraison_id'] = this.adresseLivraisonId;
    data['date_commande'] = this.dateCommande;
    data['montant_total'] = this.montantTotal;
    data['etat_commande'] = this.etatCommande;
    data['date_livraison'] = this.dateLivraison;
    data['date_fin_livraison'] = this.dateFinLivraison;
    data['statut'] = this.statut;
    data['note'] = this.note;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['remise'] = this.remise;
    data['montant_tva'] = this.montant_tva;
    data['cout_livraison_client'] = this.cout_livraison_client;
    data['type_livraison_id'] = this.typeLivraisonId;
    data['mode_paiement'] = this.modePaiement;
    data['adresse'] = this.adresse;
    data['numero_bl'] = this.numero_bl;
    data['fichier_bl'] = this.fichier_bl;
    return data;
  }
}

class Commande {
  int? code;
  String? message;
  DataCommande? data;

  Commande({this.code, this.message, this.data});

  Commande.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new DataCommande.fromJson(json['data']) : null;
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

class DataCommande {
  List<DetailsCommande>? commande;
  List<DetailsLocation>? location;

  DataCommande({this.commande, this.location});

  DataCommande.fromJson(Map<String, dynamic> json) {
    if (json['commande'] != null) {
      commande = <DetailsCommande>[];
      json['commande'].forEach((v) {
        commande!.add(new DetailsCommande.fromJson(v));
      });
    }
    if (json['location'] != null) {
      location = <DetailsLocation>[];
      json['location'].forEach((v) {
        location!.add(new DetailsLocation.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.commande != null) {
      data['commande'] = this.commande!.map((v) => v.toJson()).toList();
    }
    if (this.location != null) {
      data['location'] = this.location!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class DetailsCommande {
  int? id;
  String? numero;
  int? devisId;
  int? clientId;
  int? modePaiementId;
  int? adresseLivraisonId;
  String? dateCommande;
  double? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  int? statut;
  String? note;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  double? remise;
  int? typeLivraisonId;
  String? modePaiement;
  String? adresse;

  DetailsCommande(
      {this.id,
        this.numero,
        this.devisId,
        this.clientId,
        this.modePaiementId,
        this.adresseLivraisonId,
        this.dateCommande,
        this.montantTotal,
        this.etatCommande,
        this.dateLivraison,
        this.dateFinLivraison,
        this.statut,
        this.note,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.remise,
        this.typeLivraisonId,
        this.modePaiement,
        this.adresse});

  DetailsCommande.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    devisId = json['devis_id'];
    clientId = json['client_id'];
    modePaiementId = json['mode_paiement_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    dateCommande = json['date_commande'];
    montantTotal = double.parse(json['montant_total'].toString());
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    statut = json['statut'];
    note = json['note'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    remise = double.parse(json['remise'].toString());
    typeLivraisonId = json['type_livraison_id'];
    modePaiement = json['mode_paiement'];
    adresse = json['adresse'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['devis_id'] = this.devisId;
    data['client_id'] = this.clientId;
    data['mode_paiement_id'] = this.modePaiementId;
    data['adresse_livraison_id'] = this.adresseLivraisonId;
    data['date_commande'] = this.dateCommande;
    data['montant_total'] = this.montantTotal;
    data['etat_commande'] = this.etatCommande;
    data['date_livraison'] = this.dateLivraison;
    data['date_fin_livraison'] = this.dateFinLivraison;
    data['statut'] = this.statut;
    data['note'] = this.note;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['remise'] = this.remise;
    data['type_livraison_id'] = this.typeLivraisonId;
    data['mode_paiement'] = this.modePaiement;
    data['adresse'] = this.adresse;
    return data;
  }
}

class DetailsLocation {
  int? id;
  String? numero;
  int? clientId;
  int? modePaiementId;
  int? adresseLivraisonId;
  String? dateLocation;
  double? montantTotal;
  String? etatLocation;
  String? note;
  double? remise;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? modePaiement;
  String? adresse;

  DetailsLocation(
      {this.id,
        this.numero,
        this.clientId,
        this.modePaiementId,
        this.adresseLivraisonId,
        this.dateLocation,
        this.montantTotal,
        this.etatLocation,
        this.note,
        this.remise,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.modePaiement,
        this.adresse});

  DetailsLocation.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    clientId = json['client_id'];
    modePaiementId = json['mode_paiement_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    dateLocation = json['date_location'];
    montantTotal = double.parse(json['montant_total'].toString());
    etatLocation = json['etat_location'];
    note = json['note'];
    remise = double.parse(json['remise'].toString());
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    modePaiement = json['mode_paiement'];
    adresse = json['adresse'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['client_id'] = this.clientId;
    data['mode_paiement_id'] = this.modePaiementId;
    data['adresse_livraison_id'] = this.adresseLivraisonId;
    data['date_location'] = this.dateLocation;
    data['montant_total'] = this.montantTotal;
    data['etat_location'] = this.etatLocation;
    data['note'] = this.note;
    data['remise'] = this.remise;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['mode_paiement'] = this.modePaiement;
    data['adresse'] = this.adresse;
    return data;
  }
}


