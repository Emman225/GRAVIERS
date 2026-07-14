import 'package:mon_gravier_com/models/ConfigModel.dart';

class DemandeLivraison {
  String dateLivraison = '';
  String note = '';
  int typeLivraison = 0;
  int adresseDepart = 0;
  int adresseDestination = 0;
  int modePaiement = 0;
  List<Produits> produits;

  DemandeLivraison({
    this.dateLivraison = '',
    this.note = '',
    this.typeLivraison = 0,
    this.adresseDepart = 0,
    this.adresseDestination = 0,
    this.modePaiement = 0,
    this.produits = const [],
  });

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['dateLivraison'] = dateLivraison;
    data['note'] = note;
    data['typeLivraison'] = typeLivraison;
    data['adresseDepart'] = adresseDepart;
    data['adresseDestination'] = adresseDestination;
    data['modePaiement'] = modePaiement;
    if (produits.isNotEmpty) {
      data['data'] = produits.map((v) => v.toJson()).toList();
    }
    return data;
  }
}
