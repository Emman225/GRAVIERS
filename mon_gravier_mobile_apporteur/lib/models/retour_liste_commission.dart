class RetourListeCommission {
  int? code;
  String? message;
  List<UneCommission>? data;

  RetourListeCommission({this.code, this.message, this.data});

  RetourListeCommission.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <UneCommission>[];
      json['data'].forEach((v) {
        data!.add(new UneCommission.fromJson(v));
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

class UneCommission {
  int? id;
  int? apporteurId;
  int? commandeId;
  double? montant;
  int? statut;
  String? createdAt;
  String? updatedAt;
  String? typeAffaire;
  int? clientId;
  double? montantTotal;
  String? nom;
  String? prenom;

  UneCommission(
      {this.id,
        this.apporteurId,
        this.commandeId,
        this.montant,
        this.statut,
        this.createdAt,
        this.updatedAt,
        this.typeAffaire,
        this.clientId,
        this.montantTotal,
        this.nom,
        this.prenom});

  UneCommission.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    apporteurId = json['apporteur_id'];
    commandeId = json['commande_id'];
    // Parsing DÉFENSIF : un champ null (ex. jointure absente côté API) faisait
    // planter double.parse("null") -> la liste ENTIÈRE restait vide.
    montant = double.tryParse((json['montant'] ?? 0).toString()) ?? 0;
    statut = json['statut'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    typeAffaire = json['type_affaire'];
    clientId = json['client_id'];
    montantTotal = double.tryParse((json['montant_total'] ?? 0).toString()) ?? 0;
    nom = json['nom'];
    prenom = json['prenom'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['apporteur_id'] = this.apporteurId;
    data['commande_id'] = this.commandeId;
    data['montant'] = this.montant;
    data['statut'] = this.statut;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['type_affaire'] = this.typeAffaire;
    data['client_id'] = this.clientId;
    data['montant_total'] = this.montantTotal;
    data['nom'] = this.nom;
    data['prenom'] = this.prenom;
    return data;
  }
}
