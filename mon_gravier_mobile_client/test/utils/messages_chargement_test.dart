import 'package:flutter_test/flutter_test.dart';

/// Reproduit la logique de globale.dart (afficherChargement / fermerChargement /
/// afficherErreur) sans dépendre d'EasyLoading, qui exige un binding Flutter.
///
/// Bug d'origine : fermerChargement() appelait EasyLoading.dismiss() sans condition.
/// Placé après un message d'erreur — ce que font 24 écrans — il effaçait ce message
/// dans la milliseconde : l'utilisateur ne voyait AUCUNE réaction (constaté sur
/// « Mot de passe oublié »).
class Ecran {
  bool _chargementEnCours = false;
  String? affichage; // null = rien à l'écran

  void afficherChargement() {
    _chargementEnCours = true;
    affichage = 'Patientez...';
  }

  void fermerChargement() {
    if (!_chargementEnCours) return; // ne touche pas à un message affiché
    _chargementEnCours = false;
    affichage = null;
  }

  void afficherErreur(dynamic message) {
    _chargementEnCours = false;
    affichage = (message == null || message.toString().trim().isEmpty)
        ? "Une erreur s'est produite, veuillez réessayer"
        : message.toString();
  }
}

void main() {
  group('Messages et indicateur de chargement', () {
    test('le message d\'erreur SURVIT à un fermerChargement() qui suit', () {
      final e = Ecran()
        ..afficherChargement()
        ..afficherErreur('Impossible de récupérer l\'utilisateur')
        ..fermerChargement();
      expect(e.affichage, 'Impossible de récupérer l\'utilisateur');
    });

    test('sans message, fermerChargement() referme bien l\'indicateur', () {
      final e = Ecran()
        ..afficherChargement()
        ..fermerChargement();
      expect(e.affichage, isNull);
    });

    test('un message nul ou vide affiche quand même un texte lisible', () {
      final e1 = Ecran()..afficherChargement()..afficherErreur(null)..fermerChargement();
      expect(e1.affichage, "Une erreur s'est produite, veuillez réessayer");

      final e2 = Ecran()..afficherChargement()..afficherErreur('   ')..fermerChargement();
      expect(e2.affichage, "Une erreur s'est produite, veuillez réessayer");
    });

    test('deux fermerChargement() consécutifs n\'effacent pas un message', () {
      final e = Ecran()
        ..afficherChargement()
        ..afficherErreur('Serveur indisponible')
        ..fermerChargement()
        ..fermerChargement();
      expect(e.affichage, 'Serveur indisponible');
    });

    test('un nouveau chargement après un message repart proprement', () {
      final e = Ecran()
        ..afficherChargement()
        ..afficherErreur('Erreur')
        ..fermerChargement()
        ..afficherChargement();
      expect(e.affichage, 'Patientez...');
      e.fermerChargement();
      expect(e.affichage, isNull);
    });
  });

  group('Mot de passe oublié — les trois chemins muets', () {
    /// Rejoue la structure du gestionnaire du bouton « Continuer ».
    String? reaction({required int statutHttp, int? codeMetier, bool exception = false}) {
      final e = Ecran()..afficherChargement();
      try {
        if (exception) throw Exception('reseau');
        if (statutHttp == 200) {
          if (codeMetier == 200) {
            e.fermerChargement();
            return 'navigation vers OTP';
          } else {
            e.afficherErreur('Impossible de récupérer l\'utilisateur');
          }
        } else {
          e.afficherErreur('Le serveur est momentanément indisponible (code $statutHttp).');
        }
      } catch (_) {
        e.afficherErreur('Impossible de contacter le serveur.');
      }
      e.fermerChargement();
      return e.affichage;
    }

    test('compte inconnu -> message visible (avant : rien)', () {
      expect(reaction(statutHttp: 200, codeMetier: 404),
          "Impossible de récupérer l'utilisateur");
    });

    test('erreur serveur -> message visible (avant : rien)', () {
      expect(reaction(statutHttp: 500), contains('momentanément indisponible'));
    });

    test('panne réseau -> message visible (avant : rien)', () {
      expect(reaction(statutHttp: 0, exception: true), contains('contacter le serveur'));
    });

    test('cas nominal -> navigation vers la saisie du code', () {
      expect(reaction(statutHttp: 200, codeMetier: 200), 'navigation vers OTP');
    });
  });
}
