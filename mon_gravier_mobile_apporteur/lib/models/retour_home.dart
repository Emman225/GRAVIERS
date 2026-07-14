import 'User.dart';

class Stats {
  num? ceMois;
  num? cetteAnnee;

  Stats({this.ceMois, this.cetteAnnee});

  Stats copyWith({num? ceMois, num? cetteAnnee}) =>
      Stats(ceMois: ceMois ?? this.ceMois,
          cetteAnnee: cetteAnnee ?? this.cetteAnnee);

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{};
    map["ce_mois"] = ceMois;
    map["cette_annee"] = cetteAnnee;
    return map;
  }

  Stats.fromJson(dynamic json){
    ceMois = num.tryParse(json["ce_mois"]?.toString() ?? '0') ?? 0;
    cetteAnnee = num.tryParse(json["cette_annee"]?.toString() ?? '0') ?? 0;
  }
}

class Paiements {
  num? id;
  num? montant;
  num? modePaiementId;
  num? userId;
  num? userValideId;
  String? dateValidation;
  num? paye;
  num? statut;
  String? createdAt;
  String? updatedAt;
  String? numeroCompte;
  num? userValide2Id;
  String? modePaiement;
  String? dateDemande;

  Paiements(
      {this.id, this.montant, this.modePaiementId, this.userId, this.userValideId, this.dateValidation, this.paye, this.statut, this.createdAt, this.updatedAt, this.numeroCompte, this.userValide2Id, this.modePaiement, this.dateDemande});

  Paiements copyWith(
      {num? id, num? montant, num? modePaiementId, num? userId, num? userValideId, String? dateValidation, num? paye, num? statut, String? createdAt, String? updatedAt, String? numeroCompte, num? userValide2Id, String? modePaiement, String? dateDemande}) =>
      Paiements(id: id ?? this.id,
          montant: montant ?? this.montant,
          modePaiementId: modePaiementId ?? this.modePaiementId,
          userId: userId ?? this.userId,
          userValideId: userValideId ?? this.userValideId,
          dateValidation: dateValidation ?? this.dateValidation,
          paye: paye ?? this.paye,
          statut: statut ?? this.statut,
          createdAt: createdAt ?? this.createdAt,
          updatedAt: updatedAt ?? this.updatedAt,
          numeroCompte: numeroCompte ?? this.numeroCompte,
          userValide2Id: userValide2Id ?? this.userValide2Id,
          modePaiement: modePaiement ?? this.modePaiement,
          dateDemande: dateDemande ?? this.dateDemande);

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{};
    map["id"] = id;
    map["montant"] = montant;
    map["mode_paiement_id"] = modePaiementId;
    map["user_id"] = userId;
    map["user_valide_id"] = userValideId;
    map["date_validation"] = dateValidation;
    map["paye"] = paye;
    map["statut"] = statut;
    map["created_at"] = createdAt;
    map["updated_at"] = updatedAt;
    map["numero_compte"] = numeroCompte;
    map["user_valide2_id"] = userValide2Id;
    map["mode_paiement"] = modePaiement;
    map["date_demande"] = dateDemande;
    return map;
  }

  Paiements.fromJson(dynamic json){
    id = json["id"];
    montant = json["montant"];
    modePaiementId = json["mode_paiement_id"];
    userId = json["user_id"];
    userValideId = json["user_valide_id"];
    dateValidation = json["date_validation"];
    paye = json["paye"];
    statut = json["statut"];
    createdAt = json["created_at"];
    updatedAt = json["updated_at"];
    numeroCompte = json["numero_compte"];
    userValide2Id = json["user_valide2_id"];
    modePaiement = json["mode_paiement"];
    dateDemande = json["date_demande"];
  }
}

class DataPaiement {
  List<Stats>? statsList;
  List<Paiements>? paiementsList;

  DataPaiement({this.statsList, this.paiementsList});

  DataPaiement copyWith({List<Stats>? statsList, List<Paiements>? paiementsList}) =>
      DataPaiement(statsList: statsList ?? this.statsList,
          paiementsList: paiementsList ?? this.paiementsList);

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{};
    if (statsList != null) {
      map["stats"] = statsList?.map((v) => v.toJson()).toList();
    }
    if (paiementsList != null) {
      map["paiements"] = paiementsList?.map((v) => v.toJson()).toList();
    }
    return map;
  }

  DataPaiement.fromJson(dynamic json){
    if (json["stats"] != null) {
      statsList = [];
      json["stats"].forEach((v) {
        statsList?.add(Stats.fromJson(v));
      });
    }
    if (json["paiements"] != null) {
      paiementsList = [];
      json["paiements"].forEach((v) {
        paiementsList?.add(Paiements.fromJson(v));
      });
    }
  }
}

class RetourHome {
  num? code;
  String? message;
  Apporteur? apporteur;
  DataPaiement? data;

  RetourHome({this.code, this.message, this.apporteur, this.data});

  RetourHome copyWith(
      {num? code, String? message, Apporteur? apporteur, DataPaiement? data}) =>
      RetourHome(code: code ?? this.code,
          message: message ?? this.message,
          apporteur: apporteur ?? this.apporteur,
          data: data ?? this.data);

  Map<String, dynamic> toJson() {
    final map = <String, dynamic>{};
    map["code"] = code;
    map["message"] = message;
    if (apporteur != null) {
      map["apporteur"] = apporteur?.toJson();
    }
    if (data != null) {
      map["data"] = data?.toJson();
    }
    return map;
  }

  RetourHome.fromJson(dynamic json){
    code = json["code"];
    message = json["message"];
    apporteur =
    json["apporteur"] != null ? Apporteur.fromJson(json["apporteur"]) : null;
    data = json["data"] != null ? DataPaiement.fromJson(json["data"]) : null;
  }
}