class RetourVehicule {
  int? code;
  String? message;
  DataRetourVehicule? data;

  RetourVehicule({this.code, this.message, this.data});

  RetourVehicule.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new DataRetourVehicule.fromJson(json['data']) : null;
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

class DataRetourVehicule {
  List<Vehicules>? vehicules;
  List<TypeVehicule>? types;

  DataRetourVehicule({this.vehicules, this.types});

  DataRetourVehicule.fromJson(Map<String, dynamic> json) {
    if (json['vehicules'] != null) {
      vehicules = <Vehicules>[];
      json['vehicules'].forEach((v) {
        vehicules!.add(new Vehicules.fromJson(v));
      });
    }
    if (json['types'] != null) {
      types = <TypeVehicule>[];
      json['types'].forEach((v) {
        types!.add(new TypeVehicule.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    if (this.vehicules != null) {
      data['vehicules'] = this.vehicules!.map((v) => v.toJson()).toList();
    }
    if (this.types != null) {
      data['types'] = this.types!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class Vehicules {
  int? id;
  String? immatriculation;
  String? nom;
  String? description;
  int? typeVehiculeId;
  String? typeVehicule;
  int? livreurId;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  bool? disponible;
  int? capacite;
  String? marque;
  String? modele;
  String? libelle;

  Vehicules(
      {this.id,
        this.immatriculation,
        this.nom,
        this.description,
        this.typeVehiculeId,
        this.typeVehicule,
        this.livreurId,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.disponible,
        this.capacite,
        this.marque,
        this.modele,
        this.libelle});

  Vehicules.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    immatriculation = json['immatriculation'];
    nom = json['nom'];
    description = json['description'];
    typeVehiculeId = json['type_vehicule_id'];
    typeVehicule = json['type_vehicule'];
    livreurId = json['livreur_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    disponible = json['disponible'] == 0 ? false : true;
    capacite = json['capacite'];
    marque = json['marque'];
    modele = json['modele'];
    libelle = json['libelle'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['immatriculation'] = this.immatriculation;
    data['nom'] = this.nom;
    data['description'] = this.description;
    data['type_vehicule_id'] = this.typeVehiculeId;
    data['type_vehicule'] = this.typeVehicule;
    data['livreur_id'] = this.livreurId;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['disponible'] = this.disponible;
    data['capacite'] = this.capacite;
    data['marque'] = this.marque;
    data['modele'] = this.modele;
    data['libelle'] = this.libelle;
    return data;
  }
}

class TypeVehicule {
  int? id;
  String? libelle;
  String? createdAt;
  String? updatedAt;

  TypeVehicule({this.id, this.libelle, this.createdAt, this.updatedAt});

  TypeVehicule.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    libelle = json['libelle'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['libelle'] = this.libelle;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    return data;
  }
}

