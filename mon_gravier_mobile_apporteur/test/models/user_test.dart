import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_apporteur/models/User.dart';

void main() {
  group('User (Apporteur)', () {
    test('fromJson() with valid JSON parses all fields correctly', () {
      final json = {
        'code': 200,
        'token': 'apporteur_token_123',
        'type': 3,
        'photo': 'apporteur_photo.jpg',
        'configs': {
          'mode_paiements': [
            {
              'id': 1,
              'libelle': 'Especes',
              'description': 'Paiement en especes',
              'statut': 1,
              'en_ligne': 0,
              'deleted_at': null,
              'created_at': '2025-01-01',
              'updated_at': '2025-01-01',
            }
          ],
          'type_livraisons': [],
          'unites': [],
          'pays': [],
          'villes': [],
        },
        'message': 'Connexion reussie',
        'nom': 'Konan Ange',
        'email': 'konan@test.com',
        'apporteur': {
          'id': 10,
          'user_id': 5,
          'code': 'APP001',
          'solde': '25000.50',
          'statut': 1,
          'created_at': '2025-01-01',
          'updated_at': '2025-01-15',
          'pourcentage': 10,
        },
      };

      final user = User.fromJson(json);

      expect(user.code, 200);
      expect(user.token, 'apporteur_token_123');
      expect(user.type, 3);
      expect(user.photo, 'apporteur_photo.jpg');
      expect(user.configs, isNotNull);
      expect(user.message, 'Connexion reussie');
      expect(user.nom, 'Konan Ange');
      expect(user.email, 'konan@test.com');
      expect(user.apporteur, isNotNull);
    });

    test('fromJson() without configs sets configs to null', () {
      final json = {
        'code': 200,
        'token': 'token',
        'type': 3,
        'photo': '',
        'configs': null,
        'message': 'OK',
        'nom': 'Test',
        'email': 'test@test.com',
        'apporteur': null,
      };

      final user = User.fromJson(json);

      expect(user.configs, isNull);
      expect(user.apporteur, isNull);
    });

    test('toJson() serializes User correctly', () {
      final user = User(
        code: 200,
        token: 'mytoken',
        type: 3,
        photo: 'photo.jpg',
        message: 'OK',
        nom: 'Dupont',
        email: 'dupont@test.com',
      );

      final json = user.toJson();

      expect(json['code'], 200);
      expect(json['token'], 'mytoken');
      expect(json['type'], 3);
      expect(json['photo'], 'photo.jpg');
      expect(json['message'], 'OK');
      expect(json['nom'], 'Dupont');
      expect(json['email'], 'dupont@test.com');
    });

    test('toJson() includes apporteur when present', () {
      final user = User(
        code: 200,
        token: 'token',
        type: 3,
        apporteur: Apporteur(
          id: 1,
          userId: 5,
          code: 'APP001',
          solde: 15000.0,
          statut: 1,
          pourcentage: 10,
        ),
      );

      final json = user.toJson();

      expect(json['apporteur'], isNotNull);
      expect(json['apporteur']['id'], 1);
      expect(json['apporteur']['code'], 'APP001');
      expect(json['apporteur']['solde'], 15000.0);
      expect(json['apporteur']['pourcentage'], 10);
    });
  });

  group('Apporteur', () {
    test('fromJson() with valid JSON parses all fields', () {
      final json = {
        'id': 10,
        'user_id': 5,
        'code': 'APP-XYZ',
        'solde': '35000.75',
        'statut': 1,
        'created_at': '2025-01-01',
        'updated_at': '2025-06-15',
        'pourcentage': 15,
      };

      final apporteur = Apporteur.fromJson(json);

      expect(apporteur.id, 10);
      expect(apporteur.userId, 5);
      expect(apporteur.code, 'APP-XYZ');
      expect(apporteur.solde, 35000.75);
      expect(apporteur.statut, 1);
      expect(apporteur.createdAt, '2025-01-01');
      expect(apporteur.updatedAt, '2025-06-15');
      expect(apporteur.pourcentage, 15);
    });

    test('fromJson() with null solde defaults to 0.0', () {
      final json = {
        'id': 11,
        'user_id': 6,
        'code': 'APP-NULL',
        'solde': null,
        'statut': 1,
        'created_at': '2025-01-01',
        'updated_at': '2025-01-01',
        'pourcentage': 5,
      };

      final apporteur = Apporteur.fromJson(json);

      expect(apporteur.solde, 0.0);
    });

    test('fromJson() with integer solde parses correctly', () {
      final json = {
        'id': 12,
        'user_id': 7,
        'code': 'APP-INT',
        'solde': 10000,
        'statut': 1,
        'created_at': '2025-01-01',
        'updated_at': '2025-01-01',
        'pourcentage': 8,
      };

      final apporteur = Apporteur.fromJson(json);

      expect(apporteur.solde, 10000.0);
    });

    test('toJson() serializes Apporteur correctly', () {
      final apporteur = Apporteur(
        id: 1,
        userId: 5,
        code: 'APP001',
        solde: 50000.0,
        statut: 1,
        createdAt: '2025-01-01',
        updatedAt: '2025-01-15',
        pourcentage: 12,
      );

      final json = apporteur.toJson();

      expect(json['id'], 1);
      expect(json['user_id'], 5);
      expect(json['code'], 'APP001');
      expect(json['solde'], 50000.0);
      expect(json['statut'], 1);
      expect(json['pourcentage'], 12);
    });
  });

  group('ConfigModel (Apporteur)', () {
    test('fromJson() parses mode_paiements correctly', () {
      final json = {
        'mode_paiements': [
          {
            'id': 1,
            'libelle': 'Especes',
            'description': 'Cash payment',
            'statut': 1,
            'en_ligne': 0,
            'deleted_at': null,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
          {
            'id': 2,
            'libelle': 'Mobile Money',
            'description': 'Payment by mobile',
            'statut': 1,
            'en_ligne': 1,
            'deleted_at': null,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
        ],
        'type_livraisons': [
          {
            'id': 1,
            'libelle': 'Standard',
            'statut': 1,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
        ],
        'unites': [],
        'pays': [],
        'villes': [],
      };

      final config = ConfigModel.fromJson(json);

      expect(config.modePaiements, isNotNull);
      expect(config.modePaiements!.length, 2);
      expect(config.modePaiements!.first.libelle, 'Especes');
      expect(config.modePaiements!.last.enLigne, 1);
      expect(config.typeLivraisons, isNotNull);
      expect(config.typeLivraisons!.length, 1);
    });

    test('fromJson() with null lists leaves fields null', () {
      final json = <String, dynamic>{};

      final config = ConfigModel.fromJson(json);

      expect(config.modePaiements, isNull);
      expect(config.typeLivraisons, isNull);
      expect(config.unites, isNull);
      expect(config.pays, isNull);
      expect(config.villes, isNull);
    });

    test('toJson() serializes ConfigModel correctly', () {
      final config = ConfigModel(
        modePaiements: [
          ModePaiements(id: 1, libelle: 'Especes', statut: 1),
        ],
        typeLivraisons: [],
        unites: [],
        pays: [],
        villes: [],
      );

      final json = config.toJson();

      expect(json['mode_paiements'], isNotNull);
      expect((json['mode_paiements'] as List).length, 1);
      expect(json['type_livraisons'], isNotNull);
    });
  });
}
