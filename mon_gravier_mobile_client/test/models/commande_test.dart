import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/models/Commande.dart';

void main() {
  group('UneCommande', () {
    test('fromJson() correctly assigns est_livrable (bug fix)', () {
      final json = {
        'id': 1,
        'numero': 'CMD001',
        'devis_id': null,
        'client_id': 10,
        'mode_paiement_id': 1,
        'adresse_livraison_id': 5,
        'date_commande': '2025-01-15',
        'montant_total': '15000',
        'etat_commande': 'EN ATTENTE',
        'date_livraison': '2025-01-20',
        'date_fin_livraison': null,
        'statut': 1,
        'note': 'Test note',
        'deleted_at': null,
        'created_at': '2025-01-15',
        'updated_at': '2025-01-15',
        'remise': '500',
        'montant_tva': '2700',
        'cout_livraison_client': '3000',
        'type_livraison_id': 2,
        'est_livrable': 1,
        'mode_paiement': 'Especes',
        'type_livraison': 'Standard',
        'adresse': '123 Rue Test',
        'numero_bl': 'BL001',
        'fichier_bl': 'file.pdf',
      };

      final commande = UneCommande.fromJson(json);

      // Bug fix: est_livrable is now correctly assigned from json['est_livrable']
      expect(commande.est_livrable, 1);
    });

    test('fromJson() correctly assigns typeLivraisonId from type_livraison_id', () {
      final json = {
        'id': 2,
        'numero': 'CMD002',
        'devis_id': null,
        'client_id': 20,
        'mode_paiement_id': 2,
        'adresse_livraison_id': 3,
        'date_commande': '2025-02-01',
        'montant_total': '25000',
        'etat_commande': 'EN TRAITEMENT',
        'date_livraison': null,
        'date_fin_livraison': null,
        'statut': 1,
        'note': '',
        'deleted_at': null,
        'created_at': '2025-02-01',
        'updated_at': '2025-02-01',
        'remise': '0',
        'montant_tva': '4500',
        'cout_livraison_client': '0',
        'type_livraison_id': 3,
        'est_livrable': 0,
        'mode_paiement': 'Virement',
        'type_livraison': 'Express',
        'adresse': '456 Avenue Test',
        'numero_bl': null,
        'fichier_bl': null,
      };

      final commande = UneCommande.fromJson(json);

      expect(commande.typeLivraisonId, 3);
    });

    test('toJson() serializes type_livraison_id from typeLivraisonId (bug fix)', () {
      final commande = UneCommande(
        id: 1,
        numero: 'CMD001',
        typeLivraisonId: 5,
        est_livrable: 1,
        montantTotal: 15000.0,
        remise: 500.0,
        montant_tva: 2700.0,
        cout_livraison_client: 3000.0,
      );

      final json = commande.toJson();

      // Bug fix: toJson now uses typeLivraisonId for 'type_livraison_id'
      expect(json['type_livraison_id'], 5);
      expect(json['id'], 1);
      expect(json['numero'], 'CMD001');
    });

    test('remise handles null values in JSON', () {
      final json = {
        'id': 3,
        'numero': 'CMD003',
        'devis_id': null,
        'client_id': 30,
        'mode_paiement_id': 1,
        'adresse_livraison_id': 1,
        'date_commande': '2025-03-01',
        'montant_total': null,
        'etat_commande': 'EN ATTENTE',
        'date_livraison': null,
        'date_fin_livraison': null,
        'statut': 1,
        'note': null,
        'deleted_at': null,
        'created_at': '2025-03-01',
        'updated_at': '2025-03-01',
        'remise': null,
        'montant_tva': null,
        'cout_livraison_client': null,
        'type_livraison_id': 1,
        'est_livrable': 0,
        'mode_paiement': 'Especes',
        'type_livraison': 'Standard',
        'adresse': 'Test',
        'numero_bl': null,
        'fichier_bl': null,
      };

      final commande = UneCommande.fromJson(json);

      // Null values should default to 0.0 via the double.parse fallback
      expect(commande.remise, 0.0);
      expect(commande.montant_tva, 0.0);
      expect(commande.cout_livraison_client, 0.0);
      expect(commande.montantTotal, 0.0);
    });

    test('montant_tva and cout_livraison_client parse string values', () {
      final json = {
        'id': 4,
        'numero': 'CMD004',
        'devis_id': null,
        'client_id': 40,
        'mode_paiement_id': 1,
        'adresse_livraison_id': 1,
        'date_commande': '2025-04-01',
        'montant_total': '50000.5',
        'etat_commande': 'TERMINEE',
        'date_livraison': '2025-04-05',
        'date_fin_livraison': null,
        'statut': 1,
        'note': '',
        'deleted_at': null,
        'created_at': '2025-04-01',
        'updated_at': '2025-04-01',
        'remise': '1000.75',
        'montant_tva': '9000.09',
        'cout_livraison_client': '5000',
        'type_livraison_id': 1,
        'est_livrable': 1,
        'mode_paiement': 'Carte',
        'type_livraison': 'Express',
        'adresse': 'Rue de Test',
        'numero_bl': 'BL004',
        'fichier_bl': null,
      };

      final commande = UneCommande.fromJson(json);

      expect(commande.montantTotal, 50000.5);
      expect(commande.remise, 1000.75);
      expect(commande.montant_tva, 9000.09);
      expect(commande.cout_livraison_client, 5000.0);
    });
  });

  group('Commande', () {
    test('fromJson() wraps response correctly with data', () {
      final json = {
        'code': 200,
        'message': 'Success',
        'data': {
          'commande': [
            {
              'id': 1,
              'numero': 'CMD001',
              'devis_id': null,
              'client_id': 10,
              'mode_paiement_id': 1,
              'adresse_livraison_id': 1,
              'date_commande': '2025-01-15',
              'montant_total': '15000',
              'etat_commande': 'EN ATTENTE',
              'date_livraison': null,
              'date_fin_livraison': null,
              'statut': 1,
              'note': '',
              'deleted_at': null,
              'created_at': '2025-01-15',
              'updated_at': '2025-01-15',
              'remise': '0',
              'type_livraison_id': 1,
              'mode_paiement': 'Especes',
              'adresse': 'Test',
            },
          ],
          'location': [],
        },
      };

      final commande = Commande.fromJson(json);

      expect(commande.code, 200);
      expect(commande.message, 'Success');
      expect(commande.data, isNotNull);
      expect(commande.data!.commande, isNotNull);
      expect(commande.data!.commande!.length, 1);
      expect(commande.data!.commande!.first.numero, 'CMD001');
      expect(commande.data!.location, isNotNull);
      expect(commande.data!.location!.length, 0);
    });

    test('fromJson() with null data sets data to null', () {
      final json = {
        'code': 404,
        'message': 'Not found',
        'data': null,
      };

      final commande = Commande.fromJson(json);

      expect(commande.code, 404);
      expect(commande.message, 'Not found');
      expect(commande.data, isNull);
    });

    test('toJson() serializes Commande correctly', () {
      final commande = Commande(
        code: 200,
        message: 'OK',
        data: DataCommande(commande: [], location: []),
      );

      final json = commande.toJson();

      expect(json['code'], 200);
      expect(json['message'], 'OK');
      expect(json['data'], isNotNull);
      expect(json['data']['commande'], []);
      expect(json['data']['location'], []);
    });
  });
}
