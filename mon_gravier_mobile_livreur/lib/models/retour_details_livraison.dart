class RetourDetailsLivraison {
  int? code;
  String? message;
  List<DataRetourDetailsLivraison>? data;

  RetourDetailsLivraison({this.code, this.message, this.data});

  RetourDetailsLivraison.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <DataRetourDetailsLivraison>[];
      json['data'].forEach((v) {
        data!.add(new DataRetourDetailsLivraison.fromJson(v));
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

class DataRetourDetailsLivraison {
  int? id;
  String? nomProduit;
  int? qte;
  String? unite;
  String? description;
  int? demandeLivraisonId;
  String? etatLivraison;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? uniteProduitId;
  String? numero;
  String? libelle;
  String? descriptionDemande;
  int? adresseLivraisonPecId;
  int? adresseLivraisonDestId;
  int? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  int? remise;
  String? affichagePec;
  String? complementAdressePec;
  String? longitudePec;
  String? latitudePec;
  String? affichageDest;
  String? complementAdresseDest;
  String? longitudeDest;
  String? latitudeDest;

  DataRetourDetailsLivraison(
      {this.id,
        this.nomProduit,
        this.qte,
        this.unite,
        this.description,
        this.demandeLivraisonId,
        this.etatLivraison,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.uniteProduitId,
        this.numero,
        this.libelle,
        this.descriptionDemande,
        this.adresseLivraisonPecId,
        this.adresseLivraisonDestId,
        this.montantTotal,
        this.etatCommande,
        this.dateLivraison,
        this.dateFinLivraison,
        this.remise,
        this.affichagePec,
        this.complementAdressePec,
        this.longitudePec,
        this.latitudePec,
        this.affichageDest,
        this.complementAdresseDest,
        this.longitudeDest,
        this.latitudeDest});

  DataRetourDetailsLivraison.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nomProduit = json['nom_produit'];
    qte = json['qte'];
    unite = json['unite'];
    description = json['description'];
    demandeLivraisonId = json['demande_livraison_id'];
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    uniteProduitId = json['unite_produit_id'];
    numero = json['numero'];
    libelle = json['libelle'];
    descriptionDemande = json['description_demande'];
    adresseLivraisonPecId = json['adresse_livraison_pec_id'];
    adresseLivraisonDestId = json['adresse_livraison_dest_id'];
    montantTotal = json['montantTotal'];
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    remise = json['remise'];
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
    data['nom_produit'] = this.nomProduit;
    data['qte'] = this.qte;
    data['unite'] = this.unite;
    data['description'] = this.description;
    data['demande_livraison_id'] = this.demandeLivraisonId;
    data['etat_livraison'] = this.etatLivraison;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['unite_produit_id'] = this.uniteProduitId;
    data['numero'] = this.numero;
    data['libelle'] = this.libelle;
    data['description_demande'] = this.descriptionDemande;
    data['adresse_livraison_pec_id'] = this.adresseLivraisonPecId;
    data['adresse_livraison_dest_id'] = this.adresseLivraisonDestId;
    data['montantTotal'] = this.montantTotal;
    data['etat_commande'] = this.etatCommande;
    data['date_livraison'] = this.dateLivraison;
    data['date_fin_livraison'] = this.dateFinLivraison;
    data['remise'] = this.remise;
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
