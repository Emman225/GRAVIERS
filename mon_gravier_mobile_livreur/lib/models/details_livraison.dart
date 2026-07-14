import 'package:flutter/foundation.dart';
import 'package:mon_gravier_com_livreur/models/RetourVehicule.dart';

class DetailsLivraison {
  int? code;
  String? message;
  LigneDetailsLivraison? data;

  DetailsLivraison({this.code, this.message, this.data});

  DetailsLivraison.fromJson(Map<String, dynamic> json) {
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
  List<Vehicules>? vehicules;

  LigneDetailsLivraison({this.ligneCommande, this.ligneLivraison});

  LigneDetailsLivraison.fromJson(Map<String, dynamic> json) {

    if (kDebugMode) {
      print("-------------->");
      print(json['ligneLivraison']);
    }

    ligneCommande = (json['ligneCommande'] != null && json['ligneCommande'] is Map)
        ? LigneCommande.fromJson(json['ligneCommande'])
        : null;
    ligneLivraison = (json['ligneLivraison'] != null && json['ligneLivraison'] is Map)
        ? LigneLivraison.fromJson(json['ligneLivraison'])
        : null;
    vehicules = <Vehicules>[];
    if (json['vehicules'] != null) {
      json['vehicules'].forEach((v) {
        vehicules!.add(Vehicules.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    if (ligneCommande != null) {
      data['ligneCommande'] = ligneCommande!.toJson();
    }
    if (ligneLivraison != null) {
      data['ligneLivraison'] = ligneLivraison!.toJson();
    }
    if (vehicules != null) {
      data['vehicules'] = vehicules!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class LigneCommande {
  int? id;
  int? produitId;
  int? commandeId;
  double? qte;
  double? prix;
  String? etatLivraison;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  double? qteLivree;
  String? reference;
  String? nom;
  String? unite;
  String? description;
  int? prixMoyen;
  int? prixReduction;
  String? image;

  LigneCommande(
      {this.id,
        this.produitId,
        this.commandeId,
        this.qte,
        this.prix,
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
    qte = double.parse((json['qte'] ?? 0).toString());
    prix = double.parse((json['prix'] ?? 0).toString());
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    // qte_livree peut arriver en int (ex. 1) alors que le champ est double? ->
    // conversion sûre (num -> double) pour éviter "int is not a subtype of double?".
    qteLivree = (json['qte_livree'] as num?)?.toDouble();
    reference = json['reference'];
    nom = json['nom'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = json['prix_moyen'];
    prixReduction = json['prix_reduction'];
    image = json['image'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['produit_id'] = this.produitId;
    data['commande_id'] = this.commandeId;
    data['qte'] = this.qte;
    data['prix'] = this.prix;
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
  int? qte;
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
  int? montantTotal;
  String? etatCommande;
  String? dateLivraison;
  String? dateFinLivraison;
  int? remise;
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
    qte = json['qte'];
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
    montantTotal = json['montantTotal'];
    etatCommande = json['etat_commande'];
    dateLivraison = json['date_livraison'];
    dateFinLivraison = json['date_fin_livraison'];
    remise = json['remise'];
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
