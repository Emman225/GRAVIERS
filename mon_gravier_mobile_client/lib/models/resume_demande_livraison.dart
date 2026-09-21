class ResumeDemandeLivraison {
  int? code;
  String? message;
  DetailResume? data;

  ResumeDemandeLivraison({this.code, this.message, this.data});

  ResumeDemandeLivraison.fromJson(Map<String, dynamic> json) {
    code = json['code'];
    message = json['message'];
    data = json['data'] != null ? new DetailResume.fromJson(json['data']) : null;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['code'] = this.code;
    data['message'] = this.message;
    if (this.data != null) {
      data['data'] = this.data!.toJson();
    }
    return data;
  }
}

class DetailResume {
  int? distance;
  // `montant` est ce que le client PAIE : taxe comprise le cas echeant. Les
  // deux champs suivants le detaillent, pour que l'ecart entre le tarif de la
  // grille et le total ne reste pas inexplique.
  int? montant;
  int? montantHt;
  int? tva;
  String? depart;
  String? destination;
  String? typeLivraison;
  String? modePaiement;
  String? dateLivraison;

  DetailResume(
      {this.distance,
        this.montant,
        this.montantHt,
        this.tva,
        this.depart,
        this.destination,
        this.typeLivraison,
        this.modePaiement,
        this.dateLivraison});

  DetailResume.fromJson(Map<String, dynamic> json) {
    distance = json['distance'];
    montant = json['montant'];
    // Champs absents si le serveur n'est pas encore a jour : le detail ne
    // s'affiche alors pas, et l'ecran continue de fonctionner comme avant.
    montantHt = json['montant_ht'];
    tva = json['tva'];
    depart = json['depart'];
    destination = json['destination'];
    typeLivraison = json['type_livraison'];
    modePaiement = json['mode_paiement'];
    dateLivraison = json['date_livraison'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['distance'] = this.distance;
    data['montant'] = this.montant;
    data['montant_ht'] = this.montantHt;
    data['tva'] = this.tva;
    data['depart'] = this.depart;
    data['destination'] = this.destination;
    data['type_livraison'] = this.typeLivraison;
    data['mode_paiement'] = this.modePaiement;
    data['date_livraison'] = this.dateLivraison;
    return data;
  }
}
