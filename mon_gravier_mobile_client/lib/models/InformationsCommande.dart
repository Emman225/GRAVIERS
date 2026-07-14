import 'dart:typed_data';

import 'Commande.dart';

class InformationsCommande {
  int? code;
  String? message;
  Data? data;

  InformationsCommande({this.code, this.message, this.data});

  InformationsCommande.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new Data.fromJson(json['data']) : null;
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

class Data {
  UneCommande? commande;
  List<LigneCommande>? lignes;
  bool? client_a_terme;

  Data({this.commande, this.lignes, this.client_a_terme});

  Data.fromJson(Map<String, dynamic> json) {
    client_a_terme = json['client_a_terme'];
    commande = json['commande'] != null
        ? new UneCommande.fromJson(json['commande'])
        : null;
    if (json['lignes'] != null) {
      lignes = <LigneCommande>[];
      json['lignes'].forEach((v) {
        lignes!.add(new LigneCommande.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.commande != null) {
      data['commande'] = this.commande!.toJson();
    }
    if (this.client_a_terme != null) {
      data['client_a_terme'] = this.client_a_terme;
    }
    if (this.lignes != null) {
      data['lignes'] = this.lignes!.map((v) => v.toJson()).toList();
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
  double? prixMoyen;
  double? prixReduction;
  String? image;
  Uint8List? imageU8List;

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
        this.imageU8List,
        this.image});

  LigneCommande.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    produitId = json['produit_id'];
    commandeId = json['commande_id'];
    qte = double.parse(json['qte']==null ? '0' : json['qte'].toString());
    prix = double.parse(json['prix']==null ? '0' : json['prix'].toString());
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


class InformationsLocation {
  int? code;
  String? message;
  DataInfosLocation? data;

  InformationsLocation({this.code, this.message, this.data});

  InformationsLocation.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new DataInfosLocation.fromJson(json['data']) : null;
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

class DataInfosLocation {
  bool? clientATerme;
  UneLocation? location;
  List<LigneLocation>? lignes;

  DataInfosLocation({this.clientATerme, this.location, this.lignes});

  DataInfosLocation.fromJson(Map<String, dynamic> json) {
    clientATerme = json['client_a_terme'];
    location = json['location'] != null
        ? new UneLocation.fromJson(json['location'])
        : null;
    if (json['lignes'] != null) {
      lignes = <LigneLocation>[];
      json['lignes'].forEach((v) {
        lignes!.add(new LigneLocation.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['client_a_terme'] = this.clientATerme;
    if (this.location != null) {
      data['location'] = this.location!.toJson();
    }
    if (this.lignes != null) {
      data['lignes'] = this.lignes!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class UneLocation {
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
  double? montant_tva;
  double? cout_livraison_client;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? modePaiement;
  String? adresse;

  UneLocation(
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
        this.montant_tva,
        this.cout_livraison_client,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.modePaiement,
        this.adresse});

  UneLocation.fromJson(Map<String, dynamic> json) {
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
    montant_tva = double.parse(json['montant_tva'].toString());
    cout_livraison_client = double.parse(json['cout_livraison_client'].toString());
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
    data['montant_tva'] = this.montant_tva;
    data['cout_livraison_client'] = this.cout_livraison_client;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['mode_paiement'] = this.modePaiement;
    data['adresse'] = this.adresse;
    return data;
  }
}

class LigneLocation {
  int? id;
  int? produitId;
  int? locationId;
  double? qte;
  String? debut;
  String? fin;
  double? prix;
  String? etatLocation;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  int? nombreJour;
  String? reference;
  String? nom;
  String? unite;
  String? description;
  double? prixMoyen;
  double? prixReduction;
  String? image;
  Uint8List? imageU8List;

  LigneLocation(
      {this.id,
        this.produitId,
        this.locationId,
        this.qte,
        this.debut,
        this.fin,
        this.prix,
        this.etatLocation,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.nombreJour,
        this.reference,
        this.nom,
        this.unite,
        this.description,
        this.prixMoyen,
        this.prixReduction,
        this.imageU8List,
        this.image});

  LigneLocation.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    produitId = json['produit_id'];
    locationId = json['location_id'];
    qte = double.parse(json['qte'].toString());
    debut = json['debut'];
    fin = json['fin'];
    prix = double.parse(json['prix'].toString());
    etatLocation = json['etat_location'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    nombreJour = json['nombre_jour'];
    reference = json['reference'];
    nom = json['nom'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = double.parse(json['prix_moyen'].toString());
    prixReduction = double.parse(json['prix_reduction'].toString());
    image = json['image'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['produit_id'] = this.produitId;
    data['location_id'] = this.locationId;
    data['qte'] = this.qte;
    data['debut'] = this.debut;
    data['fin'] = this.fin;
    data['prix'] = this.prix;
    data['etat_location'] = this.etatLocation;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['nombre_jour'] = this.nombreJour;
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

