/// Une facture DGI du client, telle que le site la tient (lot 95, 16/09/2026) :
/// vente, location, transport ou avoir, certifiée ou en attente.
class FactureDgi {
  int? id;
  String? numero;
  String? reference;
  String? typeDocument;
  String? libelle;
  String? service;
  int? serviceId;
  String? numAffaire;
  double? montant;
  String? date;
  bool certifiee = false;
  String? motifAvoir;
  String? origineNumero;
  String? lienVerification;

  FactureDgi();

  bool get estUnAvoir => (typeDocument ?? '').toUpperCase() == 'AVOIR';

  FactureDgi.fromJson(Map<String, dynamic> json) {
    id = json['id'] == null ? null : int.tryParse(json['id'].toString());
    numero = json['numero']?.toString();
    reference = json['reference']?.toString();
    typeDocument = json['type_document']?.toString();
    libelle = json['libelle']?.toString();
    service = json['service']?.toString();
    serviceId = json['service_id'] == null ? null : int.tryParse(json['service_id'].toString());
    numAffaire = json['num_affaire']?.toString();
    montant = json['montant'] == null ? null : double.tryParse(json['montant'].toString());
    date = json['date']?.toString();
    certifiee = json['certifiee'] == true || json['certifiee'] == 1 || json['certifiee'] == '1';
    motifAvoir = json['motif_avoir']?.toString();
    origineNumero = json['origine_numero']?.toString();
    lienVerification = json['lien_verification']?.toString();
  }
}
