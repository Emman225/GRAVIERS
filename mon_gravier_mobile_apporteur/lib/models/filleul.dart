class RetourFilleul {
  int? code;
  String? message;
  List<UnFilleul>? data;

  RetourFilleul({this.code, this.message, this.data});

  RetourFilleul.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <UnFilleul>[];
      json['data'].forEach((v) {
        data!.add(new UnFilleul.fromJson(v));
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

class UnFilleul {
  int? id;
  int? userId;
  String? nom;
  String? prenom;
  String? email;
  String? contact1;
  String? contact2;
  String? codeParrain;
  String? rccmClt;
  String? nccClt;
  int? parrainId;
  String? typeClient;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? photo;

  UnFilleul(
      {this.id,
        this.userId,
        this.nom,
        this.prenom,
        this.email,
        this.contact1,
        this.contact2,
        this.codeParrain,
        this.rccmClt,
        this.nccClt,
        this.parrainId,
        this.typeClient,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.photo,
      });

  UnFilleul.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    userId = json['user_id'];
    nom = json['nom'];
    prenom = json['prenom'];
    email = json['email'];
    contact1 = json['contact1'];
    contact2 = json['contact2'];
    codeParrain = json['code_parrain'];
    rccmClt = json['rccm_clt'];
    nccClt = json['ncc_clt'];
    parrainId = json['parrain_id'];
    typeClient = json['type_client'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    photo = json['photo'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['user_id'] = userId;
    data['nom'] = nom;
    data['prenom'] = prenom;
    data['email'] = email;
    data['contact1'] = contact1;
    data['contact2'] = contact2;
    data['code_parrain'] = codeParrain;
    data['rccm_clt'] = rccmClt;
    data['ncc_clt'] = nccClt;
    data['parrain_id'] = parrainId;
    data['type_client'] = typeClient;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['photo'] = photo;
    return data;
  }
}

