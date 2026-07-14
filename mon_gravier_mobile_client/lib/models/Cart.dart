import 'package:mon_gravier_com/models/ConfigModel.dart';


class Cart {
  Produits product;
  double numOfItem;
  int type;
  int? nbreJours;
  String? dateDebut;
  String? dateDeFin;

  Cart({required this.product, required this.numOfItem, required this.type, this.dateDebut, this.dateDeFin, this.nbreJours});
}

// Demo data for our cart

// List<Cart> demoCarts = [
//   // Cart(product: demoProducts[0], numOfItem: 2),
//   // Cart(product: demoProducts[1], numOfItem: 1),
//   // Cart(product: demoProducts[3], numOfItem: 1),
// ];
