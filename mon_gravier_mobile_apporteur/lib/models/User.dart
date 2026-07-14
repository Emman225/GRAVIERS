class User {
  int? code;
  String? token;
  int? type;
  String? photo;
  ConfigModel? configs;
  String? message;
  String? nom;
  String? email;
  Apporteur? apporteur;

  User(
      {this.code,
        this.token,
        this.type,
        this.photo,
        this.configs,
        this.message,
        this.nom,
        this.email,
        this.apporteur});

  User.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    token = json['token'];
    type = json['type'];
    photo = json['photo'];
    configs =
    json['configs'] != null ? new ConfigModel.fromJson(json['configs']) : null;
    message = json['message'];
    nom = json['nom'];
    email = json['email'];
    apporteur = json['apporteur'] != null
        ? new Apporteur.fromJson(json['apporteur'])
        : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['code'] = this.code;
    data['token'] = this.token;
    data['type'] = this.type;
    data['photo'] = this.photo;
    if (this.configs != null) {
      data['configs'] = this.configs!.toJson();
    }
    data['message'] = this.message;
    data['nom'] = this.nom;
    data['email'] = this.email;
    if (this.apporteur != null) {
      data['apporteur'] = this.apporteur!.toJson();
    }
    return data;
  }
}

class ConfigModel {
  List<ModePaiements>? modePaiements;
  List<TypeLivraisons>? typeLivraisons;
  List<Unites>? unites;
  List<Pays>? pays;
  List<Villes>? villes;

  ConfigModel(
      {this.modePaiements,
        this.typeLivraisons,
        this.unites,
        this.pays,
        this.villes});

  ConfigModel.fromJson(Map<String, dynamic> json) {
    if (json['mode_paiements'] != null) {
      modePaiements = <ModePaiements>[];
      json['mode_paiements'].forEach((v) {
        modePaiements!.add(new ModePaiements.fromJson(v));
      });
    }
    if (json['type_livraisons'] != null) {
      typeLivraisons = <TypeLivraisons>[];
      json['type_livraisons'].forEach((v) {
        typeLivraisons!.add(new TypeLivraisons.fromJson(v));
      });
    }
    if (json['unites'] != null) {
      unites = <Unites>[];
      json['unites'].forEach((v) {
        unites!.add(new Unites.fromJson(v));
      });
    }
    if (json['pays'] != null) {
      pays = <Pays>[];
      json['pays'].forEach((v) {
        pays!.add(new Pays.fromJson(v));
      });
    }
    if (json['villes'] != null) {
      villes = <Villes>[];
      json['villes'].forEach((v) {
        villes!.add(new Villes.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.modePaiements != null) {
      data['mode_paiements'] =
          this.modePaiements!.map((v) => v.toJson()).toList();
    }
    if (this.typeLivraisons != null) {
      data['type_livraisons'] =
          this.typeLivraisons!.map((v) => v.toJson()).toList();
    }
    if (this.unites != null) {
      data['unites'] = this.unites!.map((v) => v.toJson()).toList();
    }
    if (this.pays != null) {
      data['pays'] = this.pays!.map((v) => v.toJson()).toList();
    }
    if (this.villes != null) {
      data['villes'] = this.villes!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class ModePaiements {
  int? id;
  String? libelle;
  String? description;
  int? statut;
  int? enLigne;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  ModePaiements(
      {this.id,
        this.libelle,
        this.description,
        this.statut,
        this.enLigne,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  ModePaiements.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    libelle = json['libelle'];
    description = json['description'];
    statut = json['statut'];
    enLigne = json['en_ligne'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['libelle'] = this.libelle;
    data['description'] = this.description;
    data['statut'] = this.statut;
    data['en_ligne'] = this.enLigne;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
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

class Unites {
  int? id;
  String? abreviation;
  String? libelle;
  String? createdAt;
  String? updatedAt;

  Unites(
      {this.id,
        this.abreviation,
        this.libelle,
        this.createdAt,
        this.updatedAt});

  Unites.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    abreviation = json['abreviation'];
    libelle = json['libelle'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['abreviation'] = this.abreviation;
    data['libelle'] = this.libelle;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

class Pays {
  int? id;
  String? nom;
  String? code;
  String? indicatif;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Pays(
      {this.id,
        this.nom,
        this.code,
        this.indicatif,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

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
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['nom'] = this.nom;
    data['code'] = this.code;
    data['indicatif'] = this.indicatif;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

class Villes {
  int? id;
  String? nom;
  int? paysId;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Villes(
      {this.id,
        this.nom,
        this.paysId,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  Villes.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nom = json['nom'];
    paysId = json['pays_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['nom'] = this.nom;
    data['pays_id'] = this.paysId;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

class Apporteur {
  int? id;
  int? userId;
  String? code;
  double? solde;
  int? statut;
  String? createdAt;
  String? updatedAt;
  int? pourcentage;

  Apporteur(
      {this.id,
        this.userId,
        this.code,
        this.solde,
        this.statut,
        this.createdAt,
        this.updatedAt,
        this.pourcentage});

  Apporteur.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    code = json['code'];
    solde = json['solde'] != null ? double.tryParse(json['solde'].toString()) ?? 0.0 : 0.0;
    statut = json['statut'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    pourcentage = json['pourcentage'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['user_id'] = this.userId;
    data['code'] = this.code;
    data['solde'] = this.solde;
    data['statut'] = this.statut;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['pourcentage'] = this.pourcentage;
    return data;
  }
}

