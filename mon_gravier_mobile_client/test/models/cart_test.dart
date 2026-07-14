import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';

void main() {
  group('Cart', () {
    late Produits testProduct;

    setUp(() {
      testProduct = Produits(
        id: 1,
        reference: 'REF001',
        nom: 'Gravier fin',
        abreviation: 'GF',
        unite: 'Tonne',
        unite_id: 1,
        description: 'Gravier fin pour construction',
        prixMoyen: 5000,
        prixReduction: 0,
        meilleurNote: 5,
        statut: 1,
        image: 'gravier.jpg',
        type_affaire: 'VENTE',
      );
    });

    test('constructor with required fields creates valid Cart', () {
      final cart = Cart(
        product: testProduct,
        numOfItem: 3.0,
        type: 1,
      );

      expect(cart.product, isNotNull);
      expect(cart.product.nom, 'Gravier fin');
      expect(cart.numOfItem, 3.0);
      expect(cart.type, 1);
      expect(cart.nbreJours, isNull);
      expect(cart.dateDebut, isNull);
      expect(cart.dateDeFin, isNull);
    });

    test('constructor with optional nbreJours for LOCATION type', () {
      final cart = Cart(
        product: testProduct,
        numOfItem: 2.0,
        type: 2,
        nbreJours: 7,
      );

      expect(cart.nbreJours, 7);
      expect(cart.numOfItem, 2.0);
      expect(cart.type, 2);
    });

    test('constructor with optional dateDebut and dateDeFin', () {
      final cart = Cart(
        product: testProduct,
        numOfItem: 1.0,
        type: 2,
        nbreJours: 5,
        dateDebut: '2025-06-01',
        dateDeFin: '2025-06-06',
      );

      expect(cart.dateDebut, '2025-06-01');
      expect(cart.dateDeFin, '2025-06-06');
      expect(cart.nbreJours, 5);
    });

    test('Cart with product having prixPersonnalise uses effective price', () {
      final productWithCustomPrice = Produits(
        id: 2,
        nom: 'Sable',
        prixMoyen: 5000,
        prixPersonnalise: 4500.0,
        type_affaire: 'VENTE',
      );

      final cart = Cart(
        product: productWithCustomPrice,
        numOfItem: 10.0,
        type: 1,
      );

      expect(cart.product.aPrixPersonnalise, true);
      expect(cart.product.prixEffectif, 4500);
    });

    test('Cart with product without prixPersonnalise uses prixMoyen', () {
      final cart = Cart(
        product: testProduct,
        numOfItem: 5.0,
        type: 1,
      );

      expect(cart.product.aPrixPersonnalise, false);
      expect(cart.product.prixEffectif, 5000);
    });
  });
}
