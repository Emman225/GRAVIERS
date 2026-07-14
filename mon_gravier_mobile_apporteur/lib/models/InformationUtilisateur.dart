class InformationUtilisateur {
  int? code;
  String? message;
  Infos? data;

  InformationUtilisateur({this.code, this.message, this.data});

  InformationUtilisateur.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? Infos.fromJson(json['data']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['code'] = code;
    data['message'] = message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class Infos {
  int? id;
  String? nomPrenoms;
  String? email;
  String? contact;
  String? login;
  String? photo;
  String? adresse;
  int? paysId;
  int? villeId;
  int? typeUserId;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  Infos(
      {this.id,
        this.nomPrenoms,
        this.email,
        this.contact,
        this.login,
        this.photo,
        this.adresse,
        this.paysId,
        this.villeId,
        this.typeUserId,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  Infos.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nomPrenoms = json['nom_prenoms'];
    email = json['email'];
    contact = json['contact'];
    login = json['login'];
    photo = json['photo'];
    adresse = json['adresse'];
    paysId = json['pays_id'];
    villeId = json['ville_id'];
    typeUserId = json['type_user_id'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['nom_prenoms'] = nomPrenoms;
    data['email'] = email;
    data['contact'] = contact;
    data['login'] = login;
    data['photo'] = photo;
    data['adresse'] = adresse;
    data['pays_id'] = paysId;
    data['ville_id'] = villeId;
    data['type_user_id'] = typeUserId;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}
