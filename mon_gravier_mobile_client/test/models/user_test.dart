import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/models/User.dart';

void main() {
  group('User', () {
    test('default constructor initializes with default values', () {
      final user = User();
      expect(user.code, isNull);
      expect(user.token, isNull);
      expect(user.photo, isNull);
      expect(user.type, isNull);
      expect(user.configs, isNull);
      expect(user.data, isNull);
      expect(user.message, isNull);
      expect(user.nom, isNull);
      expect(user.code_parrain, isNull);
      expect(user.tva, isNull);
      expect(user.devise, isNull);
      expect(user.clientATerme, isNull);
    });

    test('fromJson() with valid JSON parses all fields correctly', () {
      final json = {
        'code': 100,
        'token': 'abc123token',
        'photo': 'photo_url.jpg',
        'type': 2,
        'data': 'some_data',
        'message': 'Success',
        'nom': 'Jean Dupont',
        'code_parrain': 'PARRAIN01',
        'tva': 18,
        'devise': 'FCFA',
        'cat': 0,
      };

      final user = User.fromJson(json);

      expect(user.code, 100);
      expect(user.token, 'abc123token');
      expect(user.photo, 'photo_url.jpg');
      expect(user.type, 2);
      expect(user.data, 'some_data');
      expect(user.message, 'Success');
      expect(user.nom, 'Jean Dupont');
      expect(user.code_parrain, 'PARRAIN01');
      expect(user.tva, 18);
      expect(user.devise, 'FCFA');
      expect(user.configs, isNull);
    });

    test('fromJson() with code=200 parses configs', () {
      final json = {
        'code': 200,
        'token': 'token123',
        'photo': '',
        'type': 1,
        'configs': {
          'categories': [],
          'bannieres': [],
          'produits': [],
          'mode_paiements': [],
          'type_livraisons': [],
          'pays': [],
          'villes': [],
          'type_users': [],
          'unites': [],
          'regions': [],
          'url_fichier': 'https://example.com/files/',
        },
        'data': '',
        'message': 'OK',
        'nom': 'Test User',
        'code_parrain': '',
        'tva': 18,
        'devise': 'FCFA',
        'cat': 1,
      };

      final user = User.fromJson(json);

      expect(user.code, 200);
      expect(user.configs, isNotNull);
      expect(user.configs!.urlFichier, 'https://example.com/files/');
    });

    test('fromJson() with code!=200 sets configs to null', () {
      final json = {
        'code': 401,
        'token': '',
        'photo': '',
        'type': 0,
        'configs': {
          'categories': [],
        },
        'data': '',
        'message': 'Unauthorized',
        'nom': '',
        'code_parrain': '',
        'tva': 0,
        'devise': '',
        'cat': 0,
      };

      final user = User.fromJson(json);

      expect(user.code, 401);
      expect(user.configs, isNull);
    });

    test('toJson() serializes correctly including data field (bug fix)', () {
      final user = User(
        code: 200,
        token: 'mytoken',
        photo: 'photo.jpg',
        type: 1,
        data: 'important_data_value',
        message: 'Hello',
        nom: 'Test',
        code_parrain: 'CP001',
        tva: 18,
        devise: 'FCFA',
        clientATerme: true,
      );

      final json = user.toJson();

      expect(json['code'], 200);
      expect(json['token'], 'mytoken');
      expect(json['photo'], 'photo.jpg');
      expect(json['type'], 1);
      // This tests the bug fix: data['data'] = this.data
      // Previously data['data'] would reference the local variable, not the field
      expect(json['data'], 'important_data_value');
      expect(json['message'], 'Hello');
      expect(json['nom'], 'Test');
      expect(json['code_parrain'], 'CP001');
      expect(json['tva'], 18);
      expect(json['devise'], 'FCFA');
      expect(json['cat'], true);
    });

    test('clientATerme is true when json cat==1', () {
      final json = {
        'code': 404,
        'token': '',
        'photo': '',
        'type': 0,
        'data': '',
        'message': '',
        'nom': '',
        'code_parrain': '',
        'tva': 0,
        'devise': '',
        'cat': 1,
      };

      final user = User.fromJson(json);
      expect(user.clientATerme, true);
    });

    test('clientATerme is false when json cat!=1', () {
      final jsonCat0 = {
        'code': 404,
        'token': '',
        'photo': '',
        'type': 0,
        'data': '',
        'message': '',
        'nom': '',
        'code_parrain': '',
        'tva': 0,
        'devise': '',
        'cat': 0,
      };

      final user0 = User.fromJson(jsonCat0);
      expect(user0.clientATerme, false);

      final jsonCatNull = {
        'code': 404,
        'token': '',
        'photo': '',
        'type': 0,
        'data': '',
        'message': '',
        'nom': '',
        'code_parrain': '',
        'tva': 0,
        'devise': '',
        'cat': null,
      };

      final userNull = User.fromJson(jsonCatNull);
      expect(userNull.clientATerme, false);

      final jsonCat2 = {
        'code': 404,
        'token': '',
        'photo': '',
        'type': 0,
        'data': '',
        'message': '',
        'nom': '',
        'code_parrain': '',
        'tva': 0,
        'devise': '',
        'cat': 2,
      };

      final user2 = User.fromJson(jsonCat2);
      expect(user2.clientATerme, false);
    });
  });
}
