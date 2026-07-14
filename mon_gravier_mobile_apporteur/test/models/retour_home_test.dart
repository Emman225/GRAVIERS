import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_apporteur/models/retour_home.dart';
import 'package:mon_gravier_com_apporteur/models/User.dart';

void main() {
  group('Stats', () {
    test('fromJson() with valid data parses correctly', () {
      final json = {
        'ce_mois': 150000,
        'cette_annee': 1200000,
      };

      final stats = Stats.fromJson(json);

      expect(stats.ceMois, 150000);
      expect(stats.cetteAnnee, 1200000);
    });

    test('fromJson() with null ce_mois and cette_annee', () {
      final json = {
        'ce_mois': null,
        'cette_annee': null,
      };

      final stats = Stats.fromJson(json);

      expect(stats.ceMois, isNull);
      expect(stats.cetteAnnee, isNull);
    });

    test('fromJson() with double values', () {
      final json = {
        'ce_mois': 75000.5,
        'cette_annee': 500000.25,
      };

      final stats = Stats.fromJson(json);

      expect(stats.ceMois, 75000.5);
      expect(stats.cetteAnnee, 500000.25);
    });

    test('toJson() produces correct output', () {
      final stats = Stats(ceMois: 100000, cetteAnnee: 800000);

      final json = stats.toJson();

      expect(json['ce_mois'], 100000);
      expect(json['cette_annee'], 800000);
    });

    test('copyWith() creates modified copy', () {
      final original = Stats(ceMois: 100, cetteAnnee: 500);
      final copy = original.copyWith(ceMois: 200);

      expect(copy.ceMois, 200);
      expect(copy.cetteAnnee, 500);
    });
  });

  group('Paiements', () {
    test('fromJson() parses all fields correctly', () {
      final json = {
        'id': 1,
        'montant': 25000,
        'mode_paiement_id': 2,
        'user_id': 5,
        'user_valide_id': 10,
        'date_validation': '2025-03-15',
        'paye': 1,
        'statut': 1,
        'created_at': '2025-03-01',
        'updated_at': '2025-03-15',
        'numero_compte': '0012345678',
        'user_valide2_id': 11,
        'mode_paiement': 'Mobile Money',
        'date_demande': '2025-03-01',
      };

      final paiement = Paiements.fromJson(json);

      expect(paiement.id, 1);
      expect(paiement.montant, 25000);
      expect(paiement.modePaiementId, 2);
      expect(paiement.userId, 5);
      expect(paiement.userValideId, 10);
      expect(paiement.dateValidation, '2025-03-15');
      expect(paiement.paye, 1);
      expect(paiement.statut, 1);
      expect(paiement.createdAt, '2025-03-01');
      expect(paiement.updatedAt, '2025-03-15');
      expect(paiement.numeroCompte, '0012345678');
      expect(paiement.userValide2Id, 11);
      expect(paiement.modePaiement, 'Mobile Money');
      expect(paiement.dateDemande, '2025-03-01');
    });

    test('toJson() serializes all fields correctly', () {
      final paiement = Paiements(
        id: 2,
        montant: 50000,
        modePaiementId: 1,
        userId: 3,
        userValideId: null,
        dateValidation: null,
        paye: 0,
        statut: 0,
        createdAt: '2025-04-01',
        updatedAt: '2025-04-01',
        numeroCompte: '9876543210',
        modePaiement: 'Especes',
        dateDemande: '2025-04-01',
      );

      final json = paiement.toJson();

      expect(json['id'], 2);
      expect(json['montant'], 50000);
      expect(json['mode_paiement_id'], 1);
      expect(json['user_id'], 3);
      expect(json['user_valide_id'], isNull);
      expect(json['paye'], 0);
      expect(json['numero_compte'], '9876543210');
      expect(json['mode_paiement'], 'Especes');
      expect(json['date_demande'], '2025-04-01');
    });

    test('copyWith() creates modified copy', () {
      final original = Paiements(id: 1, montant: 1000, paye: 0);
      final copy = original.copyWith(paye: 1, montant: 2000);

      expect(copy.id, 1);
      expect(copy.montant, 2000);
      expect(copy.paye, 1);
    });
  });

  group('DataPaiement', () {
    test('fromJson() with stats and paiements lists', () {
      final json = {
        'stats': [
          {'ce_mois': 100000, 'cette_annee': 500000},
        ],
        'paiements': [
          {
            'id': 1,
            'montant': 25000,
            'mode_paiement_id': 1,
            'user_id': 5,
            'user_valide_id': null,
            'date_validation': null,
            'paye': 0,
            'statut': 1,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
            'numero_compte': null,
            'user_valide2_id': null,
            'mode_paiement': 'Especes',
            'date_demande': '2025-01-01',
          },
          {
            'id': 2,
            'montant': 50000,
            'mode_paiement_id': 2,
            'user_id': 5,
            'user_valide_id': 10,
            'date_validation': '2025-02-01',
            'paye': 1,
            'statut': 1,
            'created_at': '2025-02-01',
            'updated_at': '2025-02-15',
            'numero_compte': '123456',
            'user_valide2_id': null,
            'mode_paiement': 'Mobile Money',
            'date_demande': '2025-02-01',
          },
        ],
      };

      final dataPaiement = DataPaiement.fromJson(json);

      expect(dataPaiement.statsList, isNotNull);
      expect(dataPaiement.statsList!.length, 1);
      expect(dataPaiement.statsList!.first.ceMois, 100000);
      expect(dataPaiement.paiementsList, isNotNull);
      expect(dataPaiement.paiementsList!.length, 2);
      expect(dataPaiement.paiementsList!.first.montant, 25000);
      expect(dataPaiement.paiementsList!.last.montant, 50000);
    });

    test('fromJson() with empty/null lists', () {
      final jsonEmpty = {
        'stats': [],
        'paiements': [],
      };

      final dataPaiementEmpty = DataPaiement.fromJson(jsonEmpty);
      // Empty arrays are still not null; they are empty lists but the forEach
      // just does nothing, so both lists are initialized but empty
      expect(dataPaiementEmpty.statsList, isNotNull);
      expect(dataPaiementEmpty.statsList!.length, 0);
      expect(dataPaiementEmpty.paiementsList, isNotNull);
      expect(dataPaiementEmpty.paiementsList!.length, 0);

      final jsonNull = {
        'stats': null,
        'paiements': null,
      };

      final dataPaiementNull = DataPaiement.fromJson(jsonNull);
      expect(dataPaiementNull.statsList, isNull);
      expect(dataPaiementNull.paiementsList, isNull);
    });

    test('toJson() produces correct output', () {
      final dataPaiement = DataPaiement(
        statsList: [Stats(ceMois: 100, cetteAnnee: 500)],
        paiementsList: [],
      );

      final json = dataPaiement.toJson();

      expect(json['stats'], isNotNull);
      expect((json['stats'] as List).length, 1);
      expect(json['paiements'], isNotNull);
      expect((json['paiements'] as List).length, 0);
    });
  });

  group('RetourHome', () {
    test('fromJson() full response parsing', () {
      final json = {
        'code': 200,
        'message': 'Success',
        'apporteur': {
          'id': 10,
          'user_id': 5,
          'code': 'APP001',
          'solde': '35000',
          'statut': 1,
          'created_at': '2025-01-01',
          'updated_at': '2025-01-15',
          'pourcentage': 10,
        },
        'data': {
          'stats': [
            {'ce_mois': 150000, 'cette_annee': 1200000},
          ],
          'paiements': [
            {
              'id': 1,
              'montant': 25000,
              'mode_paiement_id': 1,
              'user_id': 5,
              'user_valide_id': null,
              'date_validation': null,
              'paye': 0,
              'statut': 1,
              'created_at': '2025-01-01',
              'updated_at': '2025-01-01',
              'numero_compte': null,
              'user_valide2_id': null,
              'mode_paiement': 'Especes',
              'date_demande': '2025-01-01',
            },
          ],
        },
      };

      final retourHome = RetourHome.fromJson(json);

      expect(retourHome.code, 200);
      expect(retourHome.message, 'Success');
      expect(retourHome.apporteur, isNotNull);
      expect(retourHome.apporteur!.code, 'APP001');
      expect(retourHome.apporteur!.solde, 35000.0);
      expect(retourHome.data, isNotNull);
      expect(retourHome.data!.statsList, isNotNull);
      expect(retourHome.data!.statsList!.length, 1);
      expect(retourHome.data!.paiementsList!.length, 1);
    });

    test('fromJson() with null apporteur and data', () {
      final json = {
        'code': 401,
        'message': 'Non autorise',
        'apporteur': null,
        'data': null,
      };

      final retourHome = RetourHome.fromJson(json);

      expect(retourHome.code, 401);
      expect(retourHome.message, 'Non autorise');
      expect(retourHome.apporteur, isNull);
      expect(retourHome.data, isNull);
    });

    test('toJson() produces correct output', () {
      final retourHome = RetourHome(
        code: 200,
        message: 'OK',
        apporteur: Apporteur(id: 1, code: 'APP01', solde: 1000.0),
        data: DataPaiement(statsList: [], paiementsList: []),
      );

      final json = retourHome.toJson();

      expect(json['code'], 200);
      expect(json['message'], 'OK');
      expect(json['apporteur'], isNotNull);
      expect(json['apporteur']['code'], 'APP01');
      expect(json['data'], isNotNull);
    });

    test('copyWith() creates modified copy', () {
      final original = RetourHome(code: 200, message: 'OK');
      final copy = original.copyWith(message: 'Updated');

      expect(copy.code, 200);
      expect(copy.message, 'Updated');
    });
  });
}
