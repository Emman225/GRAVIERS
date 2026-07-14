class RetourListeDemandeLivraison {
  int? code;
  String? message;
  List<DataListeDemandeLivraison>? data;

  RetourListeDemandeLivraison({this.code, this.message, this.data});

  RetourListeDemandeLivraison.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <DataListeDemandeLivraison>[];
      json['data'].forEach((v) {
        data!.add(new DataListeDemandeLivraison.fromJson(v));
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

class DataListeDemandeLivraison {
  int? id;
  String? numero;
  String? libelle;
  String? description;
  int? clientId;
  int? adresseLivraisonPecId;
  int? adresseLivraisonDestId;
  int? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  int? remise;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? modePaiementId;
  int? typeLivraisonId;
  String? modePaiement;
  String? affichagePec;
  String? complementAdressePec;
  String? longitudePec;
  String? latitudePec;
  String? affichageDest;
  String? complementAdresseDest;
  String? longitudeDest;
  String? latitudeDest;

  DataListeDemandeLivraison(
      {this.id,
        this.numero,
        this.libelle,
        this.description,
        this.clientId,
        this.adresseLivraisonPecId,
        this.adresseLivraisonDestId,
        this.montantTotal,
        this.etatCommande,
        this.dateLivraison,
        this.dateFinLivraison,
        this.remise,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.modePaiementId,
        this.typeLivraisonId,
        this.modePaiement,
        this.affichagePec,
        this.complementAdressePec,
        this.longitudePec,
        this.latitudePec,
        this.affichageDest,
        this.complementAdresseDest,
        this.longitudeDest,
        this.latitudeDest});

  DataListeDemandeLivraison.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    libelle = json['libelle'];
    description = json['description'];
    clientId = json['client_id'];
    adresseLivraisonPecId = json['adresse_livraison_pec_id'];
    adresseLivraisonDestId = json['adresse_livraison_dest_id'];
    montantTotal = json['montantTotal'];
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    remise = json['remise'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    modePaiementId = json['mode_paiement_id'];
    typeLivraisonId = json['type_livraison_id'];
    modePaiement = json['mode_paiement'];
    affichagePec = json['affichage_pec'];
    complementAdressePec = json['complement_adresse_pec'];
    longitudePec = json['longitude_pec'];
    latitudePec = json['latitude_pec'];
    affichageDest = json['affichage_dest'];
    complementAdresseDest = json['complement_adresse_dest'];
    longitudeDest = json['longitude_dest'];
    latitudeDest = json['latitude_dest'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['libelle'] = this.libelle;
    data['description'] = this.description;
    data['client_id'] = this.clientId;
    data['adresse_livraison_pec_id'] = this.adresseLivraisonPecId;
    data['adresse_livraison_dest_id'] = this.adresseLivraisonDestId;
    data['montantTotal'] = this.montantTotal;
    data['etat_commande'] = this.etatCommande;
    data['date_livraison'] = this.dateLivraison;
    data['date_fin_livraison'] = this.dateFinLivraison;
    data['remise'] = this.remise;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['mode_paiement_id'] = this.modePaiementId;
    data['type_livraison_id'] = this.typeLivraisonId;
    data['mode_paiement'] = this.modePaiement;
    data['affichage_pec'] = this.affichagePec;
    data['complement_adresse_pec'] = this.complementAdressePec;
    data['longitude_pec'] = this.longitudePec;
    data['latitude_pec'] = this.latitudePec;
    data['affichage_dest'] = this.affichageDest;
    data['complement_adresse_dest'] = this.complementAdresseDest;
    data['longitude_dest'] = this.longitudeDest;
    data['latitude_dest'] = this.latitudeDest;
    return data;
  }
}

