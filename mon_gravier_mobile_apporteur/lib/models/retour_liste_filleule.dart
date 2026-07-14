class RetourListeFilleule {
  int? code;
  String? message;
  List<Filleule>? data;

  RetourListeFilleule({this.code, this.message, this.data});

  RetourListeFilleule.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <Filleule>[];
      json['data'].forEach((v) {
        data!.add(new Filleule.fromJson(v));
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

class Filleule {
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
  String? createdAt;
  String? updatedAt;
  int? appliqueTva;
  bool? clientATerme;
  int? point;

  Filleule(
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
        this.createdAt,
        this.updatedAt,
        this.appliqueTva,
        this.clientATerme,
        this.point});

  Filleule.fromJson(Map<String, dynamic> json) {
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
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    appliqueTva = json['applique_tva'];
    clientATerme = json['client_a_terme'] == 1 ? true : false;
    point = json['point'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['user_id'] = this.userId;
    data['nom'] = this.nom;
    data['prenom'] = this.prenom;
    data['email'] = this.email;
    data['contact1'] = this.contact1;
    data['contact2'] = this.contact2;
    data['code_parrain'] = this.codeParrain;
    data['rccm_clt'] = this.rccmClt;
    data['ncc_clt'] = this.nccClt;
    data['parrain_id'] = this.parrainId;
    data['type_client'] = this.typeClient;
    data['statut'] = this.statut;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['applique_tva'] = this.appliqueTva;
    data['client_a_terme'] = this.clientATerme;
    data['point'] = this.point;
    return data;
  }
}
