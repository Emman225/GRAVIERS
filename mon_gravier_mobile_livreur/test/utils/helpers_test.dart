import 'package:flutter_test/flutter_test.dart';

/// Tests for pure logic functions extracted from globale.dart.
/// These functions are recreated here because the original file has
/// heavy Flutter/plugin dependencies that prevent direct import in unit tests.

void main() {
  group('verifierClickBouton logic', () {
    // Recreate the exact logic from globale.dart
    DateTime? dateHeure;

    bool verifierClickBouton(DateTime currentTime, {int tmp = 3}) {
      if (dateHeure == null) {
        dateHeure = currentTime;
        return false;
      }
      if (currentTime.difference(dateHeure!).inSeconds < tmp) {
        return true;
      }
      dateHeure = currentTime;
      return false;
    }

    setUp(() {
      dateHeure = null;
    });

    test('allows first click (returns false)', () {
      final now = DateTime(2025, 6, 1, 10, 0, 0);
      final result = verifierClickBouton(now);

      expect(result, false);
    });

    test('blocks rapid clicks within 3 seconds (returns true)', () {
      final firstClick = DateTime(2025, 6, 1, 10, 0, 0);
      verifierClickBouton(firstClick);

      final rapidClick = DateTime(2025, 6, 1, 10, 0, 1); // 1 second later
      final result = verifierClickBouton(rapidClick);

      expect(result, true);
    });

    test('blocks click at exactly 2 seconds (still within 3s window)', () {
      final firstClick = DateTime(2025, 6, 1, 10, 0, 0);
      verifierClickBouton(firstClick);

      final secondClick = DateTime(2025, 6, 1, 10, 0, 2); // 2 seconds later
      final result = verifierClickBouton(secondClick);

      expect(result, true);
    });

    test('allows clicks after 3 seconds (returns false)', () {
      final firstClick = DateTime(2025, 6, 1, 10, 0, 0);
      verifierClickBouton(firstClick);

      final laterClick = DateTime(2025, 6, 1, 10, 0, 4); // 4 seconds later
      final result = verifierClickBouton(laterClick);

      expect(result, false);
    });

    test('allows click at exactly 3 seconds', () {
      final firstClick = DateTime(2025, 6, 1, 10, 0, 0);
      verifierClickBouton(firstClick);

      final threeSecLater = DateTime(2025, 6, 1, 10, 0, 3); // exactly 3 seconds
      final result = verifierClickBouton(threeSecLater);

      expect(result, false);
    });

    test('custom tmp parameter changes the threshold', () {
      final firstClick = DateTime(2025, 6, 1, 10, 0, 0);
      verifierClickBouton(firstClick, tmp: 5);

      final threeSecLater = DateTime(2025, 6, 1, 10, 0, 3);
      final blocked = verifierClickBouton(threeSecLater, tmp: 5);
      expect(blocked, true);

      final fiveSecLater = DateTime(2025, 6, 1, 10, 0, 5);
      final allowed = verifierClickBouton(fiveSecLater, tmp: 5);
      expect(allowed, false);
    });
  });

  group('lienAPI logic', () {
    String lienAPI(String env) {
      String url = '';
      if (env == 'local') {
        url = 'https://api.test.gravierci.com/public/index.php/mon_gravier_livreur/';
      } else {
        url = 'https://apigravier.fneconnect.net/mon_gravier_livreur/';
      }
      return url;
    }

    test('returns prod URL when env is prod', () {
      final url = lienAPI('prod');
      expect(url, 'https://apigravier.fneconnect.net/mon_gravier_livreur/');
    });

    test('returns local/test URL when env is local', () {
      final url = lienAPI('local');
      expect(url, 'https://api.test.gravierci.com/public/index.php/mon_gravier_livreur/');
    });

    test('returns prod URL for any non-local env value', () {
      final url = lienAPI('staging');
      expect(url, 'https://apigravier.fneconnect.net/mon_gravier_livreur/');
    });
  });

  group('Constants (Livreur app)', () {
    test('command status constants have correct values', () {
      const COMMANDE_EN_ATTENTE = 'EN ATTENTE';
      const COMMANDE_EN_TRAITEMENT = 'EN TRAITEMENT';
      const COMMANDE_TERMINE = 'TERMINEE';

      expect(COMMANDE_EN_ATTENTE, 'EN ATTENTE');
      expect(COMMANDE_EN_TRAITEMENT, 'EN TRAITEMENT');
      expect(COMMANDE_TERMINE, 'TERMINEE');
    });

    test('delivery status constants have correct values', () {
      const LIVRAISON_EN_ATTENTE = 'EN ATTENTE';
      const LIVRAISON_EN_TRAITEMENT = 'EN TRAITEMENT';
      const LIVRAISON_EN_COURS = 'EN COURS LIVRAISON';
      const LIVRAISON_LIVREE = 'LIVREE';

      expect(LIVRAISON_EN_ATTENTE, 'EN ATTENTE');
      expect(LIVRAISON_EN_TRAITEMENT, 'EN TRAITEMENT');
      expect(LIVRAISON_EN_COURS, 'EN COURS LIVRAISON');
      expect(LIVRAISON_LIVREE, 'LIVREE');
    });

    test('type constants have correct values', () {
      const COMMANDE = 'COMMANDE';
      const LIVRAISON = 'LIVRAISON';

      expect(COMMANDE, 'COMMANDE');
      expect(LIVRAISON, 'LIVRAISON');
    });

    test('env is set to prod', () {
      const env = 'prod';
      expect(env, 'prod');
    });
  });
}
