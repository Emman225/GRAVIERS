class AdresseDeLivraison {
  int? code;
  List<UneAdresse>? data;
  String? message;
  int? tva;

  AdresseDeLivraison({this.code, this.data, this.message, this.tva});

  AdresseDeLivraison.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    if (json['data'] != null) {
      data = <UneAdresse>[];
      json['data'].forEach((v) {
        data!.add(UneAdresse.fromJson(v));
      });
    }
    message = json['message'];
    tva = json['tva'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['code'] = code;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    data['message'] = message;
    data['tva'] = tva;
    return data;
  }
}

class UneAdresse {
  int? id;
  int? clientId;
  int? paysId;
  int? villeId;
  String? complementAdresse;
  int? defaut;
  String? longitude;
  String? latitude;
  String? affichage;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;

  UneAdresse(
      {this.id,
        this.clientId,
        this.paysId,
        this.villeId,
        this.complementAdresse,
        this.defaut,
        this.longitude,
        this.latitude,
        this.affichage,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt});

  UneAdresse.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    clientId = json['client_id'];
    paysId = json['pays_id'];
    villeId = json['ville_id'];
    complementAdresse = json['complement_adresse'];
    defaut = json['defaut'];
    longitude = json['longitude'];
    latitude = json['latitude'];
    affichage = json['affichage'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['client_id'] = clientId;
    data['pays_id'] = paysId;
    data['ville_id'] = villeId;
    data['complement_adresse'] = complementAdresse;
    data['defaut'] = defaut;
    data['longitude'] = longitude;
    data['latitude'] = latitude;
    data['affichage'] = affichage;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    return data;
  }
}
