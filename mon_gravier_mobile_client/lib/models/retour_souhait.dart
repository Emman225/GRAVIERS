import 'package:mon_gravier_com/models/ConfigModel.dart';

class RetourSouhait {
  int? code;
  String? message;
  List<ListeSouhait>? data;

  RetourSouhait({this.code, this.message, this.data});

  RetourSouhait.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <ListeSouhait>[];
      json['data'].forEach((v) {
        data!.add(new ListeSouhait.fromJson(v));
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

class ListeSouhait {
  int? id;
  String? reference;
  String? nom;
  String? abreviation;
  String? unite;
  String? description;
  int? prixMoyen;
  int? prixReduction;
  double? prixPersonnalise;
  int? meilleurNote;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? type_affaire;
  int? uniteProduitId;
  String? image;
  List<ImageProduit>? images;

  ListeSouhait(
      {this.id,
        this.reference,
        this.nom,
        this.abreviation,
        this.unite,
        this.description,
        this.prixMoyen,
        this.prixReduction,
        this.prixPersonnalise,
        this.meilleurNote,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.uniteProduitId,
        this.image,
        this.type_affaire,
        this.images});

  ListeSouhait.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    reference = json['reference'];
    nom = json['nom'];
    abreviation = json['abreviation'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = json['prix_moyen'];
    prixReduction = json['prix_reduction'];
    prixPersonnalise = json['prix_personnalise'] != null ? (json['prix_personnalise'] as num).toDouble() : null;
    meilleurNote = json['meilleur_note'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    uniteProduitId = json['unite_produit_id'];
    image = json['image'];
    type_affaire = json['type_affaire'];
    if (json['images'] != null) {
      images = <ImageProduit>[];
      json['images'].forEach((v) {
        images!.add(ImageProduit.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['reference'] = this.reference;
    data['nom'] = this.nom;
    data['abreviation'] = this.abreviation;
    data['unite'] = this.unite;
    data['description'] = this.description;
    data['prix_moyen'] = this.prixMoyen;
    data['prix_reduction'] = this.prixReduction;
    data['meilleur_note'] = this.meilleurNote;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['unite_produit_id'] = this.uniteProduitId;
    data['image'] = this.image;
    data['type_affaire'] = this.type_affaire;
    if (this.images != null) {
      data['images'] = this.images!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}


