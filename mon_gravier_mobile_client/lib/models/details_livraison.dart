import 'dart:ffi';

import 'package:flutter/foundation.dart';

class DetailsLivraison {
  int? code;
  String? message;
  LigneDetailsLivraison? data;

  DetailsLivraison({this.code, this.message, this.data});

  DetailsLivraison.fromJson(Map<String, dynamic> json) {
    if (kDebugMode) {
      print(json['data']);
    }
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new LigneDetailsLivraison.fromJson(json['data']) : null;
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

class LigneDetailsLivraison {
  LigneCommande? ligneCommande;
  LigneLivraison? ligneLivraison;

  LigneDetailsLivraison({this.ligneCommande, this.ligneLivraison});

  LigneDetailsLivraison.fromJson(Map<String, dynamic> json) {
    if (kDebugMode) {
      print("--------------------------");
      print(json['ligneCommande']);
      print(json['ligneLivraison']);
    }
    ligneCommande = (json['ligneCommande'] != null && json['ligneCommande'].isNotEmpty)
        ? LigneCommande.fromJson(json['ligneCommande'])
        : null;
    ligneLivraison = (json['ligneLivraison'] != null && json['ligneLivraison'].isNotEmpty)
        ? LigneLivraison.fromJson(json['ligneLivraison'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.ligneCommande != null) {
      data['ligneCommande'] = this.ligneCommande!.toJson();
    }
    if (this.ligneLivraison != null) {
      data['ligneLivraison'] = this.ligneLivraison!.toJson();
    }
    return data;
  }
}

class LigneCommande {
  int? id;
  int? produitId;
  int? commandeId;
  int? locationId;
  double? qte;
  double? prix;
  String? etatLivraison;
  String? etatLocation;
  String? debut;
  String? fin;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  double? qteLivree;
  String? reference;
  String? nom;
  String? unite;
  String? description;
  double? prixMoyen;
  double? prixReduction;
  String? image;

  LigneCommande(
      {this.id,
        this.produitId,
        this.commandeId,
        this.locationId,
        this.debut,
        this.fin,
        this.qte,
        this.prix,
        this.etatLocation,
        this.etatLivraison,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.qteLivree,
        this.reference,
        this.nom,
        this.unite,
        this.description,
        this.prixMoyen,
        this.prixReduction,
        this.image});

  LigneCommande.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    produitId = json['produit_id'];
    commandeId = json['commande_id'];
    locationId = json['location_id'];
    debut = json['debut'];
    fin = json['fin'];
    qte = double.parse(json['qte']==null ? '0' : json['qte'].toString());
    prix = double.parse(json['prix']==null ? '0' : json['prix'].toString());
    etatLocation = json['etat_location'];
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    qteLivree = double.parse(json['qte_livree']==null ? '0' :json['qte_livree'].toString());
    reference = json['reference'];
    nom = json['nom'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = double.parse(json['prix_moyen']==null ? '0' : json['prix_moyen'].toString());
    prixReduction = double.parse(json['prix_reduction']==null ? '0' : json['prix_reduction'].toString());
    image = json['image'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['produit_id'] = this.produitId;
    data['commande_id'] = this.commandeId;
    data['location_id'] = this.locationId;
    data['debut'] = this.debut;
    data['fin'] = this.fin;
    data['qte'] = this.qte;
    data['prix'] = this.prix;
    data['etat_location'] = this.etatLocation;
    data['etat_livraison'] = this.etatLivraison;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['qte_livree'] = this.qteLivree;
    data['reference'] = this.reference;
    data['nom'] = this.nom;
    data['unite'] = this.unite;
    data['description'] = this.description;
    data['prix_moyen'] = this.prixMoyen;
    data['prix_reduction'] = this.prixReduction;
    data['image'] = this.image;
    return data;
  }
}

class LigneLivraison {
  int? id;
  String? nomProduit;
  double? qte;
  String? unite;
  String? description;
  int? poidsVehiculeSouhaite;
  int? nombreVoyage;
  int? demandeLivraisonId;
  String? etatLivraison;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? uniteProduitId;
  String? numero;
  String? libelle;
  int? clientId;
  String? affichagePec;
  String? complementAdressePec;
  String? longitudePec;
  String? latitudePec;
  String? affichageDest;
  String? complementAdresseDest;
  String? longitudeDest;
  String? latitudeDest;
  double? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  double? remise;
  String? modePaiement;
  String? typeLivraison;

  LigneLivraison(
      {this.id,
        this.nomProduit,
        this.qte,
        this.unite,
        this.description,
        this.poidsVehiculeSouhaite,
        this.nombreVoyage,
        this.demandeLivraisonId,
        this.etatLivraison,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.uniteProduitId,
        this.numero,
        this.libelle,
        this.clientId,
        this.affichagePec,
        this.complementAdressePec,
        this.longitudePec,
        this.latitudePec,
        this.affichageDest,
        this.complementAdresseDest,
        this.longitudeDest,
        this.latitudeDest,
        this.montantTotal,
        this.etatCommande,
        this.dateLivraison,
        this.dateFinLivraison,
        this.remise,
        this.modePaiement,
        this.typeLivraison});

  LigneLivraison.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nomProduit = json['nom_produit'];
    qte = double.parse(json['qte'].toString());
    unite = json['unite'];
    description = json['description'];
    poidsVehiculeSouhaite = json['poids_vehicule_souhaite'];
    nombreVoyage = json['nombre_voyage'];
    demandeLivraisonId = json['demande_livraison_id'];
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    uniteProduitId = json['unite_produit_id'];
    numero = json['numero'];
    libelle = json['libelle'];
    clientId = json['client_id'];
    affichagePec = json['affichage_pec'];
    complementAdressePec = json['complement_adresse_pec'];
    longitudePec = json['longitude_pec'];
    latitudePec = json['latitude_pec'];
    affichageDest = json['affichage_dest'];
    complementAdresseDest = json['complement_adresse_dest'];
    longitudeDest = json['longitude_dest'];
    latitudeDest = json['latitude_dest'];
    montantTotal = double.parse(json['montantTotal'].toString());
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    remise = double.parse(json['remise'] ?? '0');
    modePaiement = json['mode_paiement'];
    typeLivraison = json['type_livraison'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['nom_produit'] = this.nomProduit;
    data['qte'] = this.qte;
    data['unite'] = this.unite;
    data['description'] = this.description;
    data['poids_vehicule_souhaite'] = this.poidsVehiculeSouhaite;
    data['nombre_voyage'] = this.nombreVoyage;
    data['demande_livraison_id'] = this.demandeLivraisonId;
    data['etat_livraison'] = this.etatLivraison;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['unite_produit_id'] = this.uniteProduitId;
    data['numero'] = this.numero;
    data['libelle'] = this.libelle;
    data['client_id'] = this.clientId;
    data['affichage_pec'] = this.affichagePec;
    data['complement_adresse_pec'] = this.complementAdressePec;
    data['longitude_pec'] = this.longitudePec;
    data['latitude_pec'] = this.latitudePec;
    data['affichage_dest'] = this.affichageDest;
    data['complement_adresse_dest'] = this.complementAdresseDest;
    data['longitude_dest'] = this.longitudeDest;
    data['latitude_dest'] = this.latitudeDest;
    data['montantTotal'] = this.montantTotal;
    data['etat_commande'] = this.etatCommande;
    data['date_livraison'] = this.dateLivraison;
    data['date_fin_livraison'] = this.dateFinLivraison;
    data['remise'] = this.remise;
    data['mode_paiement'] = this.modePaiement;
    data['type_livraison'] = this.typeLivraison;
    return data;
  }
}
