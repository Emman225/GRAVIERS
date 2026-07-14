import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_livreur/models/User.dart';

void main() {
  group('User (Livreur)', () {
    test('fromJson() with valid JSON parses all fields correctly', () {
      final json = {
        'code': 200,
        'token': 'livreur_token_abc',
        'type': 4,
        'photo': 'livreur_photo.jpg',
        'configs': {
          'mode_paiements': [
            {
              'id': 1,
              'libelle': 'Especes',
              'description': 'Cash',
              'statut': 1,
              'en_ligne': 0,
              'deleted_at': null,
              'created_at': '2025-01-01',
              'updated_at': '2025-01-01',
            },
          ],
          'type_livraisons': [],
          'unites': [],
          'pays': [],
          'villes': [],
          'url_fichier': 'https://api.example.com/files/',
        },
        'message': 'Connexion reussie',
        'nom': 'Coulibaly Ibrahim',
        'email': 'coulibaly@test.com',
        'livreur': {
          'id': 20,
          'user_id': 8,
          'num_piece_identite': 'CI123456',
          'piece_recto': 'recto.jpg',
          'piece_verso': 'verso.jpg',
          'statut': 1,
          'created_at': '2025-01-01',
          'updated_at': '2025-01-15',
          'solde': 30000,
        },
      };

      final user = User.fromJson(json);

      expect(user.code, 200);
      expect(user.token, 'livreur_token_abc');
      expect(user.type, 4);
      expect(user.photo, 'livreur_photo.jpg');
      expect(user.configs, isNotNull);
      expect(user.configs!.urlFichier, 'https://api.example.com/files/');
      expect(user.message, 'Connexion reussie');
      expect(user.nom, 'Coulibaly Ibrahim');
      expect(user.email, 'coulibaly@test.com');
      expect(user.livreur, isNotNull);
      expect(user.livreur!.id, 20);
      expect(user.livreur!.numPieceIdentite, 'CI123456');
    });

    test('fromJson() without configs and livreur sets them to null', () {
      final json = {
        'code': 401,
        'token': '',
        'type': 0,
        'photo': '',
        'configs': null,
        'message': 'Non autorise',
        'nom': '',
        'email': '',
        'livreur': null,
      };

      final user = User.fromJson(json);

      expect(user.configs, isNull);
      expect(user.livreur, isNull);
      expect(user.code, 401);
    });

    test('toJson() serializes User correctly', () {
      final user = User(
        code: 200,
        token: 'token123',
        type: 4,
        photo: 'photo.jpg',
        message: 'OK',
        nom: 'Test Livreur',
        email: 'livreur@test.com',
      );

      final json = user.toJson();

      expect(json['code'], 200);
      expect(json['token'], 'token123');
      expect(json['type'], 4);
      expect(json['photo'], 'photo.jpg');
      expect(json['message'], 'OK');
      expect(json['nom'], 'Test Livreur');
      expect(json['email'], 'livreur@test.com');
    });

    test('toJson() includes livreur and configs when present', () {
      final user = User(
        code: 200,
        token: 'token',
        type: 4,
        configs: ConfigModel(
          urlFichier: 'https://example.com/',
        ),
        livreur: Livreur(
          id: 1,
          userId: 5,
          numPieceIdentite: 'ID001',
          pieceRecto: 'r.jpg',
          pieceVerso: 'v.jpg',
          statut: 1,
          solde: 5000,
        ),
      );

      final json = user.toJson();

      expect(json['configs'], isNotNull);
      expect(json['configs']['url_fichier'], 'https://example.com/');
      expect(json['livreur'], isNotNull);
      expect(json['livreur']['id'], 1);
      expect(json['livreur']['num_piece_identite'], 'ID001');
      expect(json['livreur']['solde'], 5000);
    });
  });

  group('Livreur', () {
    test('fromJson() parses all fields correctly', () {
      final json = {
        'id': 15,
        'user_id': 7,
        'num_piece_identite': 'PIECE789',
        'piece_recto': 'recto_photo.jpg',
        'piece_verso': 'verso_photo.jpg',
        'statut': 1,
        'created_at': '2025-02-01',
        'updated_at': '2025-03-01',
        'solde': 45000,
      };

      final livreur = Livreur.fromJson(json);

      expect(livreur.id, 15);
      expect(livreur.userId, 7);
      expect(livreur.numPieceIdentite, 'PIECE789');
      expect(livreur.pieceRecto, 'recto_photo.jpg');
      expect(livreur.pieceVerso, 'verso_photo.jpg');
      expect(livreur.statut, 1);
      expect(livreur.createdAt, '2025-02-01');
      expect(livreur.updatedAt, '2025-03-01');
      expect(livreur.solde, 45000);
    });

    test('toJson() serializes Livreur correctly', () {
      final livreur = Livreur(
        id: 3,
        userId: 10,
        numPieceIdentite: 'CNI12345',
        pieceRecto: 'front.jpg',
        pieceVerso: 'back.jpg',
        statut: 1,
        createdAt: '2025-01-01',
        updatedAt: '2025-01-15',
        solde: 20000,
      );

      final json = livreur.toJson();

      expect(json['id'], 3);
      expect(json['user_id'], 10);
      expect(json['num_piece_identite'], 'CNI12345');
      expect(json['piece_recto'], 'front.jpg');
      expect(json['piece_verso'], 'back.jpg');
      expect(json['statut'], 1);
      expect(json['solde'], 20000);
    });
  });

  group('ConfigModel (Livreur)', () {
    test('fromJson() parses all config sections', () {
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
        'unites': [
          {
            'id': 1,
            'abreviation': 'T',
            'libelle': 'Tonne',
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
        ],
        'pays': [
          {
            'id': 1,
            'nom': 'Cote d Ivoire',
            'code': 'CI',
            'indicatif': '+225',
            'statut': 1,
            'deleted_at': null,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
        ],
        'villes': [
          {
            'id': 1,
            'nom': 'Abidjan',
            'pays_id': 1,
            'statut': 1,
            'deleted_at': null,
            'created_at': '2025-01-01',
            'updated_at': '2025-01-01',
          },
        ],
        'url_fichier': 'https://api.example.com/files/',
      };

      final config = ConfigModel.fromJson(json);

      expect(config.modePaiements, isNotNull);
      expect(config.modePaiements!.length, 1);
      expect(config.typeLivraisons, isNotNull);
      expect(config.typeLivraisons!.length, 1);
      expect(config.unites, isNotNull);
      expect(config.unites!.length, 1);
      expect(config.unites!.first.abreviation, 'T');
      expect(config.pays, isNotNull);
      expect(config.pays!.length, 1);
      expect(config.pays!.first.indicatif, '+225');
      expect(config.villes, isNotNull);
      expect(config.villes!.length, 1);
      expect(config.villes!.first.nom, 'Abidjan');
      expect(config.urlFichier, 'https://api.example.com/files/');
    });

    test('toJson() serializes ConfigModel correctly', () {
      final config = ConfigModel(
        urlFichier: 'https://example.com/',
        modePaiements: [],
        typeLivraisons: [],
        unites: [],
        pays: [],
        villes: [],
      );

      final json = config.toJson();

      expect(json['url_fichier'], 'https://example.com/');
      expect(json['mode_paiements'], isNotNull);
    });
  });
}
