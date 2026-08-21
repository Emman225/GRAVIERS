class RetourListeLignePaiement {
  int? code;
  String? message;
  List<LignePaiement>? data;

  RetourListeLignePaiement({this.code, this.message, this.data});

  RetourListeLignePaiement.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    if (json['data'] != null) {
      data = <LignePaiement>[];
      json['data'].forEach((v) {
        data!.add(new LignePaiement.fromJson(v));
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

class LignePaiement {
  int? id;
  int? paiementId;
  int? modePaiementId;
  String? reference;
  String? moyenPaiement;
  String? datePaiement;
  int? montant;
  int? statut;
  String? deletedAt;
  String? createdAt;
  String? updatedAt;
  String? userId;
  String? codePaiement;
  int? serviceId;
  String? service;
  String? libelle;
  String? nom;
  String? email;
  String? contact1;
  String? adresse;
  String? pays;
  String? ville;
  String? gestionnaire;

  LignePaiement(
      {this.id,
        this.paiementId,
        this.modePaiementId,
        this.reference,
        this.moyenPaiement,
        this.datePaiement,
        this.montant,
        this.statut,
        this.deletedAt,
        this.createdAt,
        this.updatedAt,
        this.userId,
        this.codePaiement,
        this.serviceId,
        this.service,
        this.nom,
        this.email,
        this.contact1,
        this.libelle,
        this.adresse,
        this.pays,
        this.ville,
        this.gestionnaire,
      });

  /// Convertit en texte quelle que soit la forme reçue.
  ///
  /// Le serveur renvoie « user_id » sous forme de NOMBRE — c'est une clé
  /// étrangère — alors que le champ est déclaré String?. L'affectation directe
  /// levait « type 'int' is not a subtype of type 'String?' », et l'écran
  /// « Imprimer mon reçu de paiement » s'ouvrait entièrement vide : ni numéro,
  /// ni lignes, ni montants. Le reçu était inutilisable.
  static String? _texte(dynamic valeur) =>
      valeur == null ? null : valeur.toString();

  /// Convertit en entier, que le serveur envoie un entier, un décimal ou du
  /// texte. « montant » est un DOUBLE en base : un règlement à décimales
  /// aurait produit la même erreur, dans l'autre sens.
  static int? _entier(dynamic valeur) {
    if (valeur == null) return null;
    if (valeur is int) return valeur;
    if (valeur is num) return valeur.round();
    return int.tryParse(valeur.toString()) ??
        double.tryParse(valeur.toString())?.round();
  }

  LignePaiement.fromJson(Map<String, dynamic> json) {
    id = _entier(json['id']);
    paiementId = _entier(json['paiement_id']);
    modePaiementId = _entier(json['mode_paiement_id']);
    reference = _texte(json['reference']);
    moyenPaiement = _texte(json['moyen_paiement']);
    datePaiement = _texte(json['date_paiement']);
    montant = _entier(json['montant']);
    statut = _entier(json['statut']);
    deletedAt = _texte(json['deleted_at']);
    createdAt = _texte(json['created_at']);
    updatedAt = _texte(json['updated_at']);
    userId = _texte(json['user_id']);
    codePaiement = _texte(json['code_paiement']);
    serviceId = _entier(json['service_id']);
    service = _texte(json['service']);
    libelle = _texte(json['libelle']);
    nom = _texte(json['nom']);
    email = _texte(json['email']);
    contact1 = _texte(json['contact1']);
    adresse = _texte(json['adresse']);
    pays = _texte(json['pays']);
    ville = _texte(json['ville']);
    gestionnaire = _texte(json['gestionnaire']);
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['paiement_id'] = this.paiementId;
    data['mode_paiement_id'] = this.modePaiementId;
    data['reference'] = this.reference;
    data['moyen_paiement'] = this.moyenPaiement;
    data['date_paiement'] = this.datePaiement;
    data['montant'] = this.montant;
    data['statut'] = this.statut;
    data['deleted_at'] = this.deletedAt;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['user_id'] = this.userId;
    data['code_paiement'] = this.codePaiement;
    data['service_id'] = this.serviceId;
    data['service'] = this.service;
    data['libelle'] = this.libelle;
    data['nom'] = this.nom;
    data['email'] = this.email;
    data['contact1'] = this.contact1;
    data['adresse'] = this.adresse;
    data['pays'] = this.pays;
    data['ville'] = this.ville;
    data['gestionnaire'] = this.gestionnaire;
    return data;
  }
}