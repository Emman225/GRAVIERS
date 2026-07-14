import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';

void main() {
  group('RetourListeCommission', () {
    test('fromJson() with data list parses correctly', () {
      final json = {
        'code': 200,
        'message': 'Success',
        'data': [
          {
            'id': 1,
            'apporteur_id': 10,
            'commande_id': 100,
            'montant': '5000',
            'statut': 1,
            'created_at': '2025-01-15',
            'updated_at': '2025-01-15',
            'type_affaire': 'VENTE',
            'client_id': 50,
            'montant_total': '75000',
            'nom': 'Kouassi',
            'prenom': 'Jean',
          },
          {
            'id': 2,
            'apporteur_id': 10,
            'commande_id': 101,
            'montant': '7500.50',
            'statut': 1,
            'created_at': '2025-02-01',
            'updated_at': '2025-02-01',
            'type_affaire': 'LOCATION',
            'client_id': 51,
            'montant_total': '150000',
            'nom': 'Diallo',
            'prenom': 'Awa',
          },
        ],
      };

      final retour = RetourListeCommission.fromJson(json);

      expect(retour.code, 200);
      expect(retour.message, 'Success');
      expect(retour.data, isNotNull);
      expect(retour.data!.length, 2);
      expect(retour.data!.first.id, 1);
      expect(retour.data!.first.nom, 'Kouassi');
      expect(retour.data!.last.typeAffaire, 'LOCATION');
    });

    test('fromJson() with null data keeps data as null', () {
      final json = {
        'code': 200,
        'message': 'No commissions',
        'data': null,
      };

      final retour = RetourListeCommission.fromJson(json);

      expect(retour.code, 200);
      expect(retour.message, 'No commissions');
      expect(retour.data, isNull);
    });

    test('fromJson() with empty data list', () {
      final json = {
        'code': 200,
        'message': 'Empty',
        'data': [],
      };

      final retour = RetourListeCommission.fromJson(json);

      expect(retour.data, isNotNull);
      expect(retour.data!.length, 0);
    });

    test('toJson() serializes correctly', () {
      final retour = RetourListeCommission(
        code: 200,
        message: 'OK',
        data: [
          UneCommission(
            id: 1,
            apporteurId: 10,
            commandeId: 100,
            montant: 5000.0,
            statut: 1,
            typeAffaire: 'VENTE',
            clientId: 50,
            montantTotal: 75000.0,
            nom: 'Test',
            prenom: 'User',
          ),
        ],
      );

      final json = retour.toJson();

      expect(json['code'], 200);
      expect(json['message'], 'OK');
      expect(json['data'], isNotNull);
      expect((json['data'] as List).length, 1);
    });
  });

  group('UneCommission', () {
    test('fromJson() parses all fields correctly', () {
      final json = {
        'id': 5,
        'apporteur_id': 12,
        'commande_id': 200,
        'montant': '12500',
        'statut': 1,
        'created_at': '2025-03-10',
        'updated_at': '2025-03-10',
        'type_affaire': 'VENTE',
        'client_id': 80,
        'montant_total': '250000',
        'nom': 'Traore',
        'prenom': 'Moussa',
      };

      final commission = UneCommission.fromJson(json);

      expect(commission.id, 5);
      expect(commission.apporteurId, 12);
      expect(commission.commandeId, 200);
      expect(commission.montant, 12500.0);
      expect(commission.statut, 1);
      expect(commission.createdAt, '2025-03-10');
      expect(commission.updatedAt, '2025-03-10');
      expect(commission.typeAffaire, 'VENTE');
      expect(commission.clientId, 80);
      expect(commission.montantTotal, 250000.0);
      expect(commission.nom, 'Traore');
      expect(commission.prenom, 'Moussa');
    });

    test('montant handles string-to-double conversion', () {
      final json = {
        'id': 6,
        'apporteur_id': 13,
        'commande_id': 201,
        'montant': '9999.99',
        'statut': 1,
        'created_at': '2025-04-01',
        'updated_at': '2025-04-01',
        'type_affaire': 'LOCATION',
        'client_id': 81,
        'montant_total': '199999.50',
        'nom': 'Kone',
        'prenom': 'Ali',
      };

      final commission = UneCommission.fromJson(json);

      expect(commission.montant, 9999.99);
      expect(commission.montantTotal, 199999.50);
    });

    test('montant handles integer values in JSON', () {
      final json = {
        'id': 7,
        'apporteur_id': 14,
        'commande_id': 202,
        'montant': 15000,
        'statut': 0,
        'created_at': '2025-05-01',
        'updated_at': '2025-05-01',
        'type_affaire': 'VENTE',
        'client_id': 82,
        'montant_total': 300000,
        'nom': 'Bamba',
        'prenom': 'Fatou',
      };

      final commission = UneCommission.fromJson(json);

      // double.parse(json['montant'].toString()) should handle int -> string -> double
      expect(commission.montant, 15000.0);
      expect(commission.montantTotal, 300000.0);
    });

    test('toJson() serializes UneCommission correctly', () {
      final commission = UneCommission(
        id: 3,
        apporteurId: 11,
        commandeId: 150,
        montant: 7500.0,
        statut: 1,
        createdAt: '2025-02-01',
        updatedAt: '2025-02-15',
        typeAffaire: 'VENTE',
        clientId: 60,
        montantTotal: 150000.0,
        nom: 'Yao',
        prenom: 'Paul',
      );

      final json = commission.toJson();

      expect(json['id'], 3);
      expect(json['apporteur_id'], 11);
      expect(json['commande_id'], 150);
      expect(json['montant'], 7500.0);
      expect(json['statut'], 1);
      expect(json['created_at'], '2025-02-01');
      expect(json['updated_at'], '2025-02-15');
      expect(json['type_affaire'], 'VENTE');
      expect(json['client_id'], 60);
      expect(json['montant_total'], 150000.0);
      expect(json['nom'], 'Yao');
      expect(json['prenom'], 'Paul');
    });
  });
}
