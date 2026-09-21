import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/impression/totaux_document.dart';

/// LES TOTAUX D'UN DOCUMENT S'ADDITIONNENT.
///
/// Trois documents — bon de commande, devis, bon de location — annonçaient des
/// totaux qui ne correspondaient ni à leur propre tableau, ni à ce que
/// l'application affichait par ailleurs.
///
/// Les deux cas ci-dessous ne sont pas inventés : ce sont les deux bons
/// relevés en recette le 5 septembre 2026, dont l'un venait du site et l'autre
/// de l'application. Le récapitulatif du SITE, lui, affichait déjà les bons
/// montants — c'est la référence que ces essais figent.
void main() {
  group('Les totaux se calculent depuis les lignes', () {
    test('bon n° 590832 — commande passée depuis le site', () {
      // Le bon imprimé annonçait « TOTAL A PAYER 7 480 F », quand la liste de
      // l'application affichait 12 826 F pour la même commande.
      const totaux = TotauxDocument(
        htArticles: 7480,
        livraison: 4000,
        tva: 1346,
      );

      expect(totaux.htDocument, 11480,
          reason: 'Le TOTAL HT doit inclure la ligne de livraison, '
              'qui figure dans le tableau.');
      expect(totaux.ttc, 12826);
      expect(totaux.aPayer, 12826,
          reason: 'C\'est le montant que la liste de l\'application annonce.');
    });

    test('bon n° 903638 — commande passée depuis l\'application', () {
      const totaux = TotauxDocument(
        htArticles: 148500,
        livraison: 4000,
        tva: 26730,
      );

      expect(totaux.htDocument, 152500);
      expect(totaux.ttc, 179230,
          reason: 'Le TTC affiché valait 175 230 : la livraison manquait.');
      expect(totaux.aPayer, 179230);
    });

    test('le même document donne le même total, quel que soit le canal', () {
      // C'était le cœur du défaut : `montant_total` porte le HT côté site et
      // le net côté mobile. En partant des lignes, la question ne se pose plus.
      const depuisLeSite = TotauxDocument(
          htArticles: 7480, livraison: 4000, tva: 1346);
      const depuisMobile = TotauxDocument(
          htArticles: 7480, livraison: 4000, tva: 1346);

      expect(depuisLeSite.aPayer, depuisMobile.aPayer);
    });

    test('la remise se retranche du total à payer', () {
      const totaux = TotauxDocument(
        htArticles: 10000,
        livraison: 2000,
        tva: 1800,
        remise: 500,
      );

      expect(totaux.htDocument, 12000);
      expect(totaux.ttc, 13800);
      expect(totaux.aPayer, 13300);
    });

    test('sans livraison, le HT du document est celui des articles', () {
      const totaux = TotauxDocument(htArticles: 5000, tva: 900);

      expect(totaux.htDocument, 5000);
      expect(totaux.ttc, 5900);
      expect(totaux.aPayer, 5900);
    });

    test('un client exonéré ne paie que ses lignes', () {
      const totaux = TotauxDocument(htArticles: 5000, livraison: 1500);

      expect(totaux.ttc, 6500);
      expect(totaux.aPayer, 6500);
    });
  });
}
