import 'Commande.dart';

class InformationsCommande {
  int? code;
  String? message;
  Data? data;

  InformationsCommande({this.code, this.message, this.data});

  InformationsCommande.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? Data.fromJson(json['data']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['code'] = this.code;
    data['message'] = this.message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class Data {
  Commande? commande;
  List<LigneCommande>? lignes;

  Data({this.commande, this.lignes});

  Data.fromJson(Map<String, dynamic> json) {
    commande = json['commande'] != null
        ? Commande.fromJson(json['commande'])
        : null;
    if (json['lignes'] != null) {
      lignes = <LigneCommande>[];
      json['lignes'].forEach((v) {
        lignes!.add(LigneCommande.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    if (commande != null) {
      data['commande'] = commande!.toJson();
    }
    if (this.lignes != null) {
      data['lignes'] = lignes!.map((v) => v.toJson()).toList();
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
  String? createdAt;
  String? updatedAt;
  int? qteLivree;
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
    qte = double.parse(json['qte'].toString());
    prix = double.parse(json['prix'].toString());
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    qteLivree = json['qte_livree'];
    reference = json['reference'];
    nom = json['nom'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = json['prix_moyen'];
    prixReduction = json['prix_reduction'];
    image = json['image'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = this.id;
    data['produit_id'] = this.produitId;
    data['commande_id'] = this.commandeId;
    data['qte'] = this.qte;
    data['prix'] = this.prix;
    data['etat_livraison'] = this.etatLivraison;
    data['statut'] = this.statut;
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
