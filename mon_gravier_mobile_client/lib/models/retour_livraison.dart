import 'package:flutter/foundation.dart';


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
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? provenance;
  int? typeLivraisonId;
  String? nomLivreur;
  String? contactLivreur;
  String? adresse;
  String? complementAdresse;
  String? longitude;
  String? latitude;
  String? typeLivraison;
  String? code_enlevement;

  UneLivraison(
      {this.id,
        this.numero,
        this.livreurId,
        this.clientId,
        this.detailCommandeId,
        this.detailLivraisonId,
        this.adresseLivraisonId,
        this.coutLivraison,
        this.dateLivraison,
        this.qte,
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
        this.adresse,
        this.complementAdresse,
        this.longitude,
        this.latitude,
        this.code_enlevement,
        this.typeLivraison});

  UneLivraison.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    livreurId = json['livreur_id'];
    clientId = json['client_id'];
    detailCommandeId = json['detail_commande_id'];
    detailLivraisonId = json['detail_livraison_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    coutLivraison = double.parse(json['cout_livraison'].toString());
    dateLivraison = json['date_livraison'];
    qte = double.parse(json['qte'].toString());
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
    adresse = json['adresse'];
    complementAdresse = json['complement_adresse'];
    longitude = json['longitude'];
    latitude = json['latitude'];
    typeLivraison = json['type_livraison'];
    code_enlevement = json['code_enlevement'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['numero'] = this.numero;
    data['livreur_id'] = this.livreurId;
    data['client_id'] = this.clientId;
    data['detail_commande_id'] = this.detailCommandeId;
    data['detail_livraison_id'] = this.detailLivraisonId;
    data['adresse_livraison_id'] = this.adresseLivraisonId;
    data['cout_livraison'] = this.coutLivraison;
    data['date_livraison'] = this.dateLivraison;
    data['qte'] = this.qte;
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
    data['adresse'] = this.adresse;
    data['complement_adresse'] = this.complementAdresse;
    data['longitude'] = this.longitude;
    data['latitude'] = this.latitude;
    data['type_livraison'] = this.typeLivraison;
    data['code_enlevement'] = this.code_enlevement;
    return data;
  }
}

