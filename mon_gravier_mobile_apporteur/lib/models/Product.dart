
class ImageProduit {
  int? id;
  String? image;
  int? produitId;
  int? defaut;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  ImageProduit(
      {this.id,
        this.image,
        this.produitId,
        this.defaut,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  ImageProduit.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    image = json['image'];
    produitId = json['produit_id'];
    defaut = json['defaut'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['image'] = this.image;
    data['produit_id'] = this.produitId;
    data['defaut'] = this.defaut;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

class Produits {
  int? id = 0;
  String? reference = '';
  String? nom = '';
  String? abreviation = '';
  String? unite = '';
  int? unite_id = 0;
  String? description = '';
  int? prixMoyen = 0;
  int? prixReduction = 0;
  int? meilleurNote = 0;
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';
  String? image = '';

  List<ImageProduit>? images;

  Produits(
      {this.id,
        this.reference,
        this.nom,
        this.abreviation,
        this.unite,
        this.unite_id,
        this.description,
        this.prixMoyen,
        this.prixReduction,
        this.meilleurNote,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.image,
        this.images = const [],
      });

  Produits.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    reference = json['reference'];
    nom = json['nom'];
    abreviation = json['abreviation'];
    unite = json['unite'];
    unite_id = json['unite_id'];
    description = json['description'];
    prixMoyen = json['prix_moyen'];
    prixReduction = json['prix_reduction'];
    meilleurNote = json['meilleur_note'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    image = json['image'];
    if (json['images'] != null) {
      images = <ImageProduit>[];
      json['images'].forEach((v) {
        images!.add(ImageProduit.fromJson(v));
      });
    }
  }

  static fromListJson(json) {
    List<Produits> prods = [];
    json.forEach((v) {
      prods.add(Produits.fromJson(v));
    });
    return prods;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['reference'] = reference;
    data['nom'] = nom;
    data['abreviation'] = abreviation;
    data['unite'] = unite;
    data['unite_id'] = unite_id;
    data['description'] = description;
    data['prix_moyen'] = prixMoyen;
    data['prix_reduction'] = prixReduction;
    data['meilleur_note'] = meilleurNote;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['image'] = image;
    if (images != null) {
      data['images'] = images!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}