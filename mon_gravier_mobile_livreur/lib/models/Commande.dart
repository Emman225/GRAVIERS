class Commande {
  int? code;
  List<DetailsCommande>? data;
  String? message;

  Commande({this.code, this.data, this.message});

  Commande.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    if (json['data'] != null) {
      data = <DetailsCommande>[];
      json['data'].forEach((v) {
        data!.add(new DetailsCommande.fromJson(v));
      });
    }
    message = json['message'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['code'] = this.code;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    data['message'] = this.message;
    return data;
  }
}

class DetailsCommande {
  int? id;
  String? numero;
  int? devisId;
  int? clientId;
  int? modePaiementId;
  int? adresseLivraisonId;
  String? dateCommande;
  double? montantTotal;
  String? etatCommande;
  String? dateFinLivraison;
  String? note;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? modePaiement;
  String? adresse;

  DetailsCommande(
      {this.id,
        this.numero,
        this.devisId,
        this.clientId,
        this.modePaiementId,
        this.adresseLivraisonId,
        this.dateCommande,
        this.montantTotal,
        this.etatCommande,
        this.dateFinLivraison,
        this.note,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.modePaiement,
        this.adresse});

  DetailsCommande.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    numero = json['numero'];
    devisId = json['devis_id'];
    clientId = json['client_id'];
    modePaiementId = json['mode_paiement_id'];
    adresseLivraisonId = json['adresse_livraison_id'];
    dateCommande = json['date_commande'];
    montantTotal = double.parse(json['montant_total'].toString());
    etatCommande = json['etat_commande'];
    dateFinLivraison = json['date_fin_livraison'];
    note = json['note'];
    statut = json['statut'];
    deletedAt = json['deleted_at'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    modePaiement = json['mode_paiement'];
    adresse = json['adresse'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['numero'] = numero;
    data['devis_id'] = devisId;
    data['client_id'] = clientId;
    data['mode_paiement_id'] = modePaiementId;
    data['adresse_livraison_id'] = adresseLivraisonId;
    data['date_commande'] = dateCommande;
    data['montant_total'] = montantTotal;
    data['etat_commande'] = etatCommande;
    data['date_fin_livraison'] = dateFinLivraison;
    data['note'] = note;
    data['statut'] = statut;
    data['deleted_at'] = deletedAt;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['mode_paiement'] = modePaiement;
    data['adresse'] = adresse;
    return data;
  }
}

