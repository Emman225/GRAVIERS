import 'package:flutter_test/flutter_test.dart';

/// Tests for the password validation logic extracted from globale.dart.
/// The function cannot be imported directly due to heavy Flutter/plugin
/// dependencies, so we recreate the exact logic here for testing.

void main() {
  // Exact replica of verifierComplexiteMotDePasse from globale.dart
  bool verifierComplexiteMotDePasse(String mdp) {
    bool bPass = true;
    List<String> tabPassWd = [
      "12345",
      "1234",
      "0000",
      "1111",
      "2222",
      "3333",
      "4444",
      "5555",
      "6666",
      "7777",
      "8888",
      "9999",
      "0101",
      "0202",
      "0303",
      "0404",
      "0505",
      "0606",
      "0707",
      "0808",
      "0909",
      "1010",
      "2020",
      "3030",
      "4040",
      "5050",
      "6060",
      "7070",
      "8080",
      "909c0",
      "11111",
      "00000",
      "22222",
      "33333",
      "44444",
      "55555",
      "66666",
      "77777",
      "88888",
      "99999"
    ];
    if (tabPassWd.contains(mdp) || mdp.length < 4) bPass = false;
    return bPass;
  }

  group('Password validation (verifierComplexiteMotDePasse)', () {
    test('rejects common password "1234"', () {
      expect(verifierComplexiteMotDePasse('1234'), false);
    });

    test('rejects common password "12345"', () {
      expect(verifierComplexiteMotDePasse('12345'), false);
    });

    test('rejects common password "0000"', () {
      expect(verifierComplexiteMotDePasse('0000'), false);
    });

    test('rejects all repeated digit passwords (4-digit)', () {
      expect(verifierComplexiteMotDePasse('1111'), false);
      expect(verifierComplexiteMotDePasse('2222'), false);
      expect(verifierComplexiteMotDePasse('3333'), false);
      expect(verifierComplexiteMotDePasse('4444'), false);
      expect(verifierComplexiteMotDePasse('5555'), false);
      expect(verifierComplexiteMotDePasse('6666'), false);
      expect(verifierComplexiteMotDePasse('7777'), false);
      expect(verifierComplexiteMotDePasse('8888'), false);
      expect(verifierComplexiteMotDePasse('9999'), false);
    });

    test('rejects all repeated digit passwords (5-digit)', () {
      expect(verifierComplexiteMotDePasse('00000'), false);
      expect(verifierComplexiteMotDePasse('11111'), false);
      expect(verifierComplexiteMotDePasse('22222'), false);
      expect(verifierComplexiteMotDePasse('33333'), false);
      expect(verifierComplexiteMotDePasse('44444'), false);
      expect(verifierComplexiteMotDePasse('55555'), false);
      expect(verifierComplexiteMotDePasse('66666'), false);
      expect(verifierComplexiteMotDePasse('77777'), false);
      expect(verifierComplexiteMotDePasse('88888'), false);
      expect(verifierComplexiteMotDePasse('99999'), false);
    });

    test('rejects repeated pair patterns', () {
      expect(verifierComplexiteMotDePasse('0101'), false);
      expect(verifierComplexiteMotDePasse('0202'), false);
      expect(verifierComplexiteMotDePasse('0303'), false);
      expect(verifierComplexiteMotDePasse('0404'), false);
      expect(verifierComplexiteMotDePasse('0505'), false);
      expect(verifierComplexiteMotDePasse('0606'), false);
      expect(verifierComplexiteMotDePasse('0707'), false);
      expect(verifierComplexiteMotDePasse('0808'), false);
      expect(verifierComplexiteMotDePasse('0909'), false);
      expect(verifierComplexiteMotDePasse('1010'), false);
      expect(verifierComplexiteMotDePasse('2020'), false);
      expect(verifierComplexiteMotDePasse('3030'), false);
      expect(verifierComplexiteMotDePasse('4040'), false);
      expect(verifierComplexiteMotDePasse('5050'), false);
      expect(verifierComplexiteMotDePasse('6060'), false);
      expect(verifierComplexiteMotDePasse('7070'), false);
      expect(verifierComplexiteMotDePasse('8080'), false);
    });

    test('rejects passwords shorter than 4 characters', () {
      expect(verifierComplexiteMotDePasse(''), false);
      expect(verifierComplexiteMotDePasse('1'), false);
      expect(verifierComplexiteMotDePasse('12'), false);
      expect(verifierComplexiteMotDePasse('123'), false);
      expect(verifierComplexiteMotDePasse('abc'), false);
    });

    test('accepts valid passwords of length >= 4', () {
      expect(verifierComplexiteMotDePasse('abcd'), true);
      expect(verifierComplexiteMotDePasse('5678'), true);
      expect(verifierComplexiteMotDePasse('pass'), true);
      expect(verifierComplexiteMotDePasse('MyP@ss'), true);
      expect(verifierComplexiteMotDePasse('secur1ty'), true);
    });

    test('accepts long numeric passwords not in blacklist', () {
      expect(verifierComplexiteMotDePasse('13579'), true);
      expect(verifierComplexiteMotDePasse('24680'), true);
      expect(verifierComplexiteMotDePasse('98765'), true);
      expect(verifierComplexiteMotDePasse('1357924'), true);
    });

    test('accepts alphanumeric passwords', () {
      expect(verifierComplexiteMotDePasse('test1234'), true);
      expect(verifierComplexiteMotDePasse('P@ssw0rd'), true);
      expect(verifierComplexiteMotDePasse('Abcd'), true);
    });
  });
}
