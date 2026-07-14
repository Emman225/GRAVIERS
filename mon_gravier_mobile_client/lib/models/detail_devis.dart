import 'dart:typed_data';

class RetourDetailDevis {
  int? code;
  String? message;
  List<DataDetailDevis>? data;

  RetourDetailDevis({this.code, this.message, this.data});

  RetourDetailDevis.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <DataDetailDevis>[];
      json['data'].forEach((v) {
        data!.add(new DataDetailDevis.fromJson(v));
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

class DataDetailDevis {
  int? id;
  int? produitId;
  int? devisId;
  double? qte;
  double? prix;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? reference;
  String? nom;
  String? unite;
  String? description;
  double? prixMoyen;
  double? prixReduction;
  String? image;
  Uint8List? imageU8List;

  double? prix_fournisseur;
  double? cout_livraison;
  String? debut_location;
  String? fin_location;
  int? nbre_jour_location;


  DataDetailDevis(
      {this.id,
        this.produitId,
        this.devisId,
        this.qte,
        this.prix,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.reference,
        this.nom,
        this.unite,
        this.description,
        this.prixMoyen,
        this.prixReduction,
        this.imageU8List,
        this.image,
        this.prix_fournisseur,
        this.cout_livraison,
        this.debut_location,
        this.fin_location,
        this.nbre_jour_location,
      });

  DataDetailDevis.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    produitId = json['produit_id'];
    devisId = json['devis_id'];
    qte = json['qte'] == null ? 0 : double.parse(json['qte'].toString());
    prix = json['prix'] == null ? 0 : double.parse(json['prix'].toString());
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    reference = json['reference'];
    nom = json['nom'];
    unite = json['unite'];
    description = json['description'];
    prixMoyen = json['prix_moyen'] == null ? 0 : double.parse(json['prix_moyen'].toString());
    prixReduction = json['prix_reduction'] == null ? 0 : double.parse(json['prix_reduction'].toString());
    image = json['image'];
    prix_fournisseur = json['prix_fournisseur'] == null ? 0 : double.parse(json['prix_fournisseur'].toString());
    cout_livraison = json['cout_livraison'] == null ? 0 : double.parse(json['cout_livraison'].toString());
    debut_location = json['debut_location'];
    fin_location = json['fin_location'];
    nbre_jour_location = json['nbre_jour_location'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['produit_id'] = this.produitId;
    data['devis_id'] = this.devisId;
    data['qte'] = this.qte;
    data['prix'] = this.prix;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['reference'] = this.reference;
    data['nom'] = this.nom;
    data['unite'] = this.unite;
    data['description'] = this.description;
    data['prix_moyen'] = this.prixMoyen;
    data['prix_reduction'] = this.prixReduction;
    data['image'] = this.image;
    data['prix_fournisseur'] = this.prix_fournisseur;
    data['cout_livraison'] = this.cout_livraison;
    data['debut_location'] = this.debut_location;
    data['fin_location'] = this.fin_location;
    data['nbre_jour_location'] = this.nbre_jour_location;
    return data;
  }
}
