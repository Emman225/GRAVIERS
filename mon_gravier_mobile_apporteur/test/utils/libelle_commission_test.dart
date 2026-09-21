import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_apporteur/helper/libelle_commission.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';

/// UNE LIGNE DE COMMISSION N'ÉCRIT JAMAIS « null ».
///
/// Constaté en production le 01/09/2026 : l'écran « Liste de mes commissions »
/// affichait « null null (# null) — Total : 0 F — Com. : 2 047 F ».
///
/// Le libellé était interpolé directement depuis trois champs du serveur. Quand
/// la chaîne commission -> affaire -> client se rompt, les trois arrivent vides
/// et le mot « null » de Dart passe tel quel à l'écran.
///
/// La commission, elle, est DUE et son montant est juste : il vit sur la ligne.
/// On ne masque donc pas la ligne — on dit ce qu'on ne sait pas.
void main() {
  UneCommission commission({
    String? nom,
    String? prenom,
    int? clientId,
    double? total,
    String? le,
  }) =>
      UneCommission(
        id: 1,
        montant: 2047,
        nom: nom,
        prenom: prenom,
        clientId: clientId,
        montantTotal: total,
        createdAt: le,
      );

  String argent(double m) => '${m.toStringAsFixed(0)} F';

  group('libelleClient', () {
    test('ne rend jamais le mot « null »', () {
      final l = libelleClient(commission());

      expect(l.toLowerCase(), isNot(contains('null')),
          reason: 'Le libellé montre « null » : le client lit un mot de '
              'programmeur au lieu du nom de son filleul.');
    });

    test('parle le langage de l’apporteur, pas celui du code', () {
      final l = libelleClient(commission());

      // Première rédaction : « Affaire non rattachée — signalez-le à
      // l’agence ». L’apporteur ne s’est pas reconnu dedans : « affaire »
      // et « rattachée » sont des mots de la base de données. Ce qui lui
      // manque, c’est le nom de son FILLEUL.
      expect(l, contains('Filleul'));
      expect(l, isNot(contains('rattach')),
          reason: 'Vocabulaire interne : la ligne redevient illisible.');
    });

    test('affiche le nom complet et la référence quand ils existent', () {
      expect(libelleClient(commission(nom: 'YEO', prenom: 'Maï', clientId: 60)),
          'YEO Maï (# 60)');
    });

    test('n’écrit pas d’espace en trop quand le prénom manque', () {
      expect(libelleClient(commission(nom: 'YEO', clientId: 60)), 'YEO (# 60)');
    });

    test('un client connu sans nom reste identifiable par sa référence', () {
      expect(libelleClient(commission(clientId: 60)), 'Client (# 60)');
    });
  });

  group('libelleMontantAffaire', () {
    test('un montant inconnu ne s’annonce pas « 0 F »', () {
      final l = libelleMontantAffaire(commission(), argent);

      expect(l, isNot(contains('0 F')),
          reason: 'Annoncer 0 F un montant inconnu est un chiffre FAUX, '
              'pas une absence : l’apporteur croit son affaire vide.');
    });

    test('à défaut du montant, la DATE situe la commission', () {
      final l = libelleMontantAffaire(
          commission(le: '2026-09-01 17:46:00'), argent,
          formaterDate: (d) => '1 septembre 2026');

      expect(l, contains('1 septembre 2026'),
          reason: 'Sans montant NI date, la ligne ne dit rien que '
              'l’apporteur puisse rapprocher de quoi que ce soit.');
      expect(l, contains('agence'),
          reason: 'Il doit savoir à qui demander le détail.');
    });

    test('sans date non plus, la ligne dit quand même quoi faire', () {
      expect(libelleMontantAffaire(commission(), argent),
          contains('agence'));
    });

    test('un montant connu s’affiche normalement', () {
      expect(
          libelleMontantAffaire(
              commission(nom: 'YEO', clientId: 60, total: 495000), argent),
          'Total: 495000 F');
    });
  });

  group('affaireConnue', () {
    test('une commission sans client ni nom est non rattachée', () {
      expect(affaireConnue(commission()), isFalse);
    });

    test('un nom vide ne suffit pas à rattacher l’affaire', () {
      expect(affaireConnue(commission(nom: '   ')), isFalse,
          reason: 'Un nom fait d’espaces afficherait une ligne muette.');
    });

    test('une référence client suffit', () {
      expect(affaireConnue(commission(clientId: 60)), isTrue);
    });
  });
  group('nomComplet', () {
    test('un prénom absent n’écrit pas « null »', () {
      // `client.prenom` est NULLABLE en base : trois clients n’en ont pas.
      expect(nomComplet('KOUASSI', null), 'KOUASSI');
    });

    test('ni espace en trop quand le prénom est vide', () {
      expect(nomComplet('KOUASSI', '   '), 'KOUASSI');
    });

    test('les deux parties se joignent par une seule espace', () {
      expect(nomComplet('YEO', 'Maï'), 'YEO Maï');
    });
  });
}
