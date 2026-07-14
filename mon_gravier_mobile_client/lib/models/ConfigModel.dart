import 'package:flutter/foundation.dart';
import 'package:mon_gravier_com/models/une_region.dart';

class ConfigModel {
  List<Categories>? categories;
  List<Bannieres>? bannieres;
  List<Produits>? produits;
  List<ModePaiements>? modePaiements;
  List<Unites>? unites;
  List<TypeLivraisons>? typeLivraisons;
  List<Pays>? pays;
  List<Ville>? villes;
  List<TypeUsers>? typeUsers;
  List<UneRegion>? regions;
  String? urlFichier = '';

  ConfigModel(
      {this.categories,
       this.bannieres,
       this.produits,
       this.modePaiements,
        this.typeLivraisons,
       this.pays,
        this.unites,
        this.regions,
       this.villes,
       this.typeUsers,
       this.urlFichier});

  ConfigModel.fromJson(Map<String, dynamic> json) {
    if (json['categories'] != null) {
      categories = <Categories>[];
      json['categories'].forEach((v) {
        categories!.add(Categories.fromJson(v));
      });
    }
    if (json['bannieres'] != null) {
      bannieres = <Bannieres>[];
      json['bannieres'].forEach((v) {
        bannieres!.add(Bannieres.fromJson(v));
      });
    }
    if (json['unites'] != null) {
      unites = <Unites>[];
      json['unites'].forEach((v) {
        unites!.add(Unites.fromJson(v));
      });
    }
    if (json['produits'] != null) {
      if (kDebugMode) {
        print(json['produits']);
      }
      produits = <Produits>[];
      json['produits'].forEach((v) {
        produits!.add(Produits.fromJson(v));
      });
    }
    if (json['mode_paiements'] != null) {
      modePaiements = <ModePaiements>[];
      json['mode_paiements'].forEach((v) {
        modePaiements!.add(ModePaiements.fromJson(v));
      });
    }
    if (json['type_livraisons'] != null) {
      typeLivraisons = <TypeLivraisons>[];
      json['type_livraisons'].forEach((v) {
        typeLivraisons!.add(new TypeLivraisons.fromJson(v));
      });
    }
    if (json['pays'] != null) {
      pays = <Pays>[];
      json['pays'].forEach((v) {
        pays!.add(Pays.fromJson(v));
      });
    }
    if (json['villes'] != null) {
      villes = <Ville>[];
      json['villes'].forEach((v) {
        villes!.add(Ville.fromJson(v));
      });
    }
    if (json['type_users'] != null) {
      typeUsers = <TypeUsers>[];
      json['type_users'].forEach((v) {
        typeUsers!.add(TypeUsers.fromJson(v));
      });
    }
    if (json['regions'] != null) {
      regions = <UneRegion>[];
      json['regions'].forEach((v) {
        regions!.add(UneRegion.fromJson(v));
      });
    }
    urlFichier = json['url_fichier'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (categories != null) {
      data['categories'] = categories!.map((v) => v.toJson()).toList();
    }
    if (bannieres != null) {
      data['bannieres'] = bannieres!.map((v) => v.toJson()).toList();
    }
    if (produits != null) {
      data['produits'] = produits!.map((v) => v.toJson()).toList();
    }
    if (modePaiements != null) {
      data['mode_paiements'] =
          modePaiements!.map((v) => v.toJson()).toList();
    }
    if (this.unites != null) {
      data['unites'] = this.unites!.map((v) => v.toJson()).toList();
    }
    if (this.typeLivraisons != null) {
      data['type_livraisons'] =
          this.typeLivraisons!.map((v) => v.toJson()).toList();
    }
    if (pays != null) {
      data['pays'] = pays!.map((v) => v.toJson()).toList();
    }
    if (villes != null) {
      data['villes'] = villes!.map((v) => v.toJson()).toList();
    }
    if (typeUsers != null) {
      data['type_users'] = typeUsers!.map((v) => v.toJson()).toList();
    }
    if (regions != null) {
      data['regions'] = regions!.map((v) => v.toJson()).toList();
    }
    data['url_fichier'] = urlFichier;
    return data;
  }
}

class Unites {
  int? id;
  String? abreviation;
  String? libelle;

  Unites(
      {this.id,
        this.abreviation,
        this.libelle,});

  Unites.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    abreviation = json['abreviation'];
    libelle = json['libelle'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['abreviation'] = this.abreviation;
    data['libelle'] = this.libelle;
    return data;
  }
}

class Categories {
  int? id = 0;
  String? image = '';
  String? nom = '';
  String? description = '';
  int? parentId = 0;
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';

  Categories(
      {id,
        image,
        nom,
        description,
        parentId,
        statut,
        deletedAt,
        createdAt,
        updatedAt});

  Categories.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    image = json['image'];
    nom = json['nom'];
    description = json['description'];
    parentId = json['parent_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = id;
    data['image'] = image;
    data['nom'] = nom;
    data['description'] = description;
    data['parent_id'] = parentId;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class Bannieres {
  int? id = 0;
  String? titre = '';
  String? sousTitre = '';
  String? image = '';
  int? numOrdre = 0;
  String? typeBanniere = '';
  String? dateHeureDecompte = '';
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';

  Bannieres(
      {id,
        titre,
        sousTitre,
        image,
        numOrdre,
        typeBanniere,
        dateHeureDecompte,
        statut,
        deletedAt,
        createdAt,
        updatedAt});

  Bannieres.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    titre = json['titre'];
    sousTitre = json['sous_titre'];
    image = json['image'];
    numOrdre = json['num_ordre'];
    typeBanniere = json['type_banniere'];
    dateHeureDecompte = json['date_heure_decompte'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['titre'] = titre;
    data['sous_titre'] = sousTitre;
    data['image'] = image;
    data['num_ordre'] = numOrdre;
    data['type_banniere'] = typeBanniere;
    data['date_heure_decompte'] = dateHeureDecompte;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

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
  int? unite_produit_id = 0;
  String? description = '';
  int? prixMoyen = 0;
  int? prixReduction = 0;
  double? prixPersonnalise;
  int? meilleurNote = 0;
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';
  String? image = '';
  String? type_affaire = '';

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
       this.prixPersonnalise,
       this.meilleurNote,
       this.statut,
       this.deletedAt,
       this.createdAt,
       this.updatedAt,
       this.image,
       this.type_affaire,
       this.images = const [],
      });

  Produits.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    reference = json['reference'];
    nom = json['nom'];
    abreviation = json['abreviation'];
    unite = json['unite'];
    unite_id = json['unite_id'];
    unite_produit_id = json['unite_produit_id'];
    description = json['description'];
    prixMoyen = json['prix_moyen'];
    prixReduction = json['prix_reduction'];
    prixPersonnalise = json['prix_personnalise'] != null ? (json['prix_personnalise'] as num).toDouble() : null;
    meilleurNote = json['meilleur_note'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    image = json['image'];
    type_affaire = json['type_affaire'];
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
    data['prix_personnalise'] = prixPersonnalise;
    data['meilleur_note'] = meilleurNote;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['image'] = image;
    data['unite_produit_id'] = unite_produit_id;
    data['type_affaire'] = type_affaire;
    if (images != null) {
      data['images'] = images!.map((v) => v.toJson()).toList();
    }
    return data;
  }

  /// Retourne le prix effectif (personnalise si disponible, sinon prix moyen)
  int get prixEffectif => prixPersonnalise != null ? prixPersonnalise!.toInt() : (prixMoyen ?? 0);

  /// Verifie si le produit a un prix personnalise
  bool get aPrixPersonnalise => prixPersonnalise != null;
}

class ModePaiements {
  int? id = 0;
  String? libelle = '';
  String? description = '';
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';

  ModePaiements(
      {id,
        libelle,
        description,
        statut,
        deletedAt,
        createdAt,
        updatedAt});

  ModePaiements.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    libelle = json['libelle'];
    description = json['description'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = id;
    data['libelle'] = libelle;
    data['description'] = description;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class TypeLivraisons {
  int? id;
  String? libelle;
  int? statut;
  String? createdAt;
  String? updatedAt;

  TypeLivraisons(
      {this.id, this.libelle, this.statut, this.createdAt, this.updatedAt});

  TypeLivraisons.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    libelle = json['libelle'];
    statut = json['statut'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['libelle'] = this.libelle;
    data['statut'] = this.statut;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

class Pays {
  int? id = 0;
  String? nom = '';
  String? code = '';
  String? indicatif = '';
  int? statut = 0;
  String? deletedAt = '';
  String? createdAt = '';
  String? updatedAt = '';

  Pays(
      {id,
        nom,
        code,
        indicatif,
        statut,
        deletedAt,
        createdAt,
        updatedAt});

  Pays.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nom = json['nom'];
    code = json['code'];
    indicatif = json['indicatif'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['nom'] = nom;
    data['code'] = code;
    data['indicatif'] = indicatif;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class Ville {
  int? id;
  String? nom;
  int? paysId;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Ville(
      {this.id,
        this.nom,
        this.paysId,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  Ville.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nom = json['nom'];
    paysId = json['pays_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['nom'] = nom;
    data['pays_id'] = paysId;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}

class TypeUsers {
  int? id = 0;
  String? nom = '';
  int? statut = 0;
  String? createdAt = '';
  String? updatedAt = '';

  TypeUsers({id, nom, statut, createdAt, updatedAt});

  TypeUsers.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nom = json['nom'];
    statut = json['statut'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['nom'] = nom;
    data['statut'] = statut;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}


