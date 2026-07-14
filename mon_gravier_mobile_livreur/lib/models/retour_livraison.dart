class RetourLivraison {
  int? code;
  String? message;
  List<UneLivraison>? data;

  RetourLivraison({this.code, this.message, this.data});

  RetourLivraison.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <UneLivraison>[];
      json['data'].forEach((v) {
        data!.add(new UneLivraison.fromJson(v));
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

class UneLivraison {
  int? id;
  String? numero;
  int? livreurId;
  int? vehiculeId;
  int? clientId;
  int? detailCommandeId;
  int? detailLivraisonId;
  int? adresseLivraisonId;
  double? coutLivraison;
  String? dateLivraison;
  double? qte;
  String? noteLivreur;
  String? etatLivraison;
  int? statut;
  int? accepte;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? provenance;
  int? typeLivraisonId;
  String? nomLivreur;
  String? contactLivreur;
  String? nomClient;
  String? contactClient;
  String? adresse;
  String? complementAdresse;
  double? longitude;
  double? latitude;
  String? typeLivraison;
  String? code_enlevement;
  String? nom_fournisseur;
  String? tel_fournisseur;
  String? adresse_fournisseur;
  double? longitude_fournisseur;
  double? latitude_fournisseur;

  UneLivraison(
      {this.id,
        this.numero,
        this.livreurId,
        this.vehiculeId,
        this.clientId,
        this.detailCommandeId,
        this.detailLivraisonId,
        this.adresseLivraisonId,
        this.coutLivraison,
        this.dateLivraison,
        this.qte,
        this.accepte,
        this.noteLivreur,
        this.etatLivraison,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.provenance,
        this.typeLivraisonId,
        this.nomLivreur,
        this.contactLivreur,
        this.nomClient,
        this.contactClient,
        this.adresse,
        this.complementAdresse,
        this.longitude,
        this.latitude,
        this.typeLivraison,
        this.code_enlevement,
        this.nom_fournisseur,
        this.tel_fournisseur,
        this.adresse_fournisseur,
        this.longitude_fournisseur,
        this.latitude_fournisseur,
      });

  UneLivraison.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    livreurId = json['livreur_id'];
    vehiculeId = json['vehicule_id'];
    clientId = json['client_id'];
    detailCommandeId = json['detail_commande_id'];
    detailLivraisonId = json['detail_livraison_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    coutLivraison = json['cout_livraison'] == null ? 0 : double.parse(json['cout_livraison'].toString());
    dateLivraison = json['date_livraison'];
    accepte = json['accepte'];
    qte = json['qte'] == null ? 0 : double.parse(json['qte'].toString());
    noteLivreur = json['note_livreur'];
    etatLivraison = json['etat_livraison'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    provenance = json['provenance'];
    typeLivraisonId = json['type_livraison_id'];
    nomLivreur = json['nom_livreur'];
    contactLivreur = json['contact_livreur'];
    nomClient = json['nom_client'];
    contactClient = json['contact_client'];
    adresse = json['adresse'];
    complementAdresse = json['complement_adresse'];
    longitude = json['longitude'] == null ? 0 : double.parse(json['longitude'].toString());
    latitude = json['latitude'] == null ? 0 : double.parse(json['latitude'].toString());
    typeLivraison = json['type_livraison'];
    code_enlevement = json['code_enlevement'];
    nom_fournisseur = json['nom_fournisseur'];
    tel_fournisseur = json['tel_fournisseur'];
    adresse_fournisseur = json['adresse_fournisseur'];
    longitude_fournisseur = json['longitude_fournisseur'] == null ? 0 : double.parse(json['longitude_fournisseur'].toString());
    latitude_fournisseur = json['latitude_fournisseur'] == null ? 0 : double.parse(json['latitude_fournisseur'].toString());
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['livreur_id'] = this.livreurId;
    data['vehicule_id'] = this.vehiculeId;
    data['client_id'] = this.clientId;
    data['detail_commande_id'] = this.detailCommandeId;
    data['detail_livraison_id'] = this.detailLivraisonId;
    data['adresse_livraison_id'] = this.adresseLivraisonId;
    data['cout_livraison'] = this.coutLivraison;
    data['date_livraison'] = this.dateLivraison;
    data['qte'] = this.qte;
    data['accepte'] = this.accepte;
    data['note_livreur'] = this.noteLivreur;
    data['etat_livraison'] = this.etatLivraison;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['provenance'] = this.provenance;
    data['type_livraison_id'] = this.typeLivraisonId;
    data['nom_livreur'] = this.nomLivreur;
    data['contact_livreur'] = this.contactLivreur;
    data['nom_client'] = this.nomClient;
    data['contact_client'] = this.contactClient;
    data['adresse'] = this.adresse;
    data['complement_adresse'] = this.complementAdresse;
    data['longitude'] = this.longitude;
    data['latitude'] = this.latitude;
    data['type_livraison'] = this.typeLivraison;
    data['code_enlevement'] = this.code_enlevement;
    data['nom_fournisseur'] = this.nom_fournisseur;
    data['tel_fournisseur'] = this.tel_fournisseur;
    data['adresse_fournisseur'] = this.adresse_fournisseur;
    data['longitude_fournisseur'] = this.longitude_fournisseur;
    data['latitude_fournisseur'] = this.latitude_fournisseur;
    return data;
  }
}

