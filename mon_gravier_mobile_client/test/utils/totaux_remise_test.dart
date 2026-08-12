import 'package:flutter_test/flutter_test.dart';

/// Vérifie la règle de calcul du net à payer côté mobile, alignée sur la
/// facture et sur le site :
///
///   remise = code promo (% du HT) + points de fidélité, plafonnée au HT
///   base   = HT - remise
///   TVA    = arrondi(base × taux)
///   net    = base + TVA
///
/// Reproduit à l'identique la logique de getTotalAmount() (globale.dart), qui ne peut
/// pas être importée telle quelle en test unitaire (dépendances Flutter/plugins).
///
/// Deux corrections successives sont gardées ici :
///
///   - les points étaient retranchés AVANT la TVA et le code promo APRÈS —
///     deux règles différentes — et rien n'empêchait un total négatif ;
///   - la TVA portait ensuite sur le HT BRUT : le client se voyait réclamer la
///     taxe sur une remise qu'il ne payait pas, et le mobile annonçait un
///     montant supérieur à celui de la facture. Elle porte désormais sur la
///     base nette, remise déduite.
class Resultat {
  final double net;
  final double remise;
  final double tva;
  Resultat(this.net, this.remise, this.tva);
}

Resultat calcul({
  required double ht,
  required int tauxTva,
  double tauxPromo = 0,
  double valeurPoints = 0,
}) {
  double total = ht;
  double coutReduction = 0;
  double montantTva = 0;

  if (tauxPromo > 0) coutReduction = total * tauxPromo / 100;
  if (valeurPoints > 0) coutReduction += valeurPoints;

  // La remise porte sur la marchandise : plafonnée au HT, jamais au-delà.
  if (coutReduction > total) coutReduction = total;

  total -= coutReduction;

  if (tauxTva > 0) {
    // Arrondi au franc, comme le serveur, pour que les deux montants coïncident.
    montantTva = (total * (tauxTva / 100)).roundToDouble();
    total += montantTva;
  } else {
    montantTva = 0;
  }

  return Resultat(total, coutReduction, montantTva);
}

void main() {
  group('Net à payer — TVA sur la base nette (convention de la facture)', () {
    test('sans remise : net = HT + TVA', () {
      final r = calcul(ht: 2000, tauxTva: 18);
      expect(r.tva, 360);
      expect(r.remise, 0);
      expect(r.net, 2360);
    });

    test('code promo 20% : la TVA porte sur le HT REMISÉ', () {
      final r = calcul(ht: 2000, tauxTva: 18, tauxPromo: 20);
      expect(r.remise, 400);
      expect(r.tva, 288, reason: 'la TVA porte sur 1600, pas sur 2000');
      expect(r.net, 1888);
    });

    test('points de fidélité : même traitement que le code promo', () {
      final r = calcul(ht: 2000, tauxTva: 18, valeurPoints: 400);
      expect(r.remise, 400);
      expect(r.tva, 288);
      expect(r.net, 1888);
    });

    test('promo ET points se cumulent dans la même ligne Remise', () {
      final r = calcul(ht: 2000, tauxTva: 18, tauxPromo: 10, valeurPoints: 200);
      expect(r.remise, 400); // 200 (10% de 2000) + 200
      expect(r.net, 1888);
    });

    test('points supérieurs au panier : le net est plafonné à 0, jamais négatif', () {
      final r = calcul(ht: 2000, tauxTva: 18, valeurPoints: 100000);
      expect(r.remise, 2000, reason: 'la remise ne dépasse pas la marchandise');
      expect(r.tva, 0, reason: 'plus de base taxable, donc plus de TVA');
      expect(r.net, 0);
      expect(r.net >= 0, isTrue);
    });

    test('client non assujetti (TVA 0) : net = HT - remise', () {
      final r = calcul(ht: 2000, tauxTva: 0, tauxPromo: 25);
      expect(r.tva, 0);
      expect(r.net, 1500);
    });

    test('commande 849677 : le mobile et la facture tombent au franc près', () {
      // Cas réel relevé en production : HT 350, remise 35 (10 %), livraison 65.
      // Le mobile réclamait 443, la facture en retenait 437. Le client avait
      // tout réglé, et son versement restait pourtant impossible à imputer :
      // la somme versée dépassait la somme due.
      final r = calcul(ht: 350, tauxTva: 18, tauxPromo: 10);
      expect(r.remise, 35);
      expect(r.tva, 57, reason: 'arrondi de 18 % de 315, soit 56,7');
      expect(r.net + 65, 437, reason: 'montant de la facture, livraison incluse');
    });
  });

  group('Affichage du panier', () {
    test('le total affiché est le net, le prix barré lui est supérieur', () {
      final r = calcul(ht: 2000, tauxTva: 18, tauxPromo: 20);
      final totalAffiche = r.net;              // getTotalAmount()
      final prixBarre = r.net + r.remise;      // total + coutReduction
      expect(totalAffiche, 1888);
      expect(prixBarre, 2288);
      expect(prixBarre > totalAffiche, isTrue, reason: 'le prix barré doit être le plus élevé');
    });
  });

  group('Nombre de jours de location', () {
    int? nombreDeJours(String d1, String d2) {
      try {
        final a = DateTime.parse(d1);
        final b = DateTime.parse(d2);
        return b.difference(a).inDays + 1;
      } catch (_) {
        return null;
      }
    }

    test('bornes incluses', () => expect(nombreDeJours('2026-08-01', '2026-08-03'), 3));
    test('même jour = 1 jour', () => expect(nombreDeJours('2026-08-01', '2026-08-01'), 1));
    test('fin avant début => valeur non positive, refusée par l\'écran', () {
      expect(nombreDeJours('2026-08-05', '2026-08-01')! <= 0, isTrue);
    });
    test('date vide => null au lieu d\'une exception', () {
      expect(nombreDeJours('', ''), isNull);
    });
  });

  group('Quantité saisie au clavier français', () {
    double? parseQte(String v) => double.tryParse(v.replaceAll(',', '.'));

    test('virgule acceptée', () => expect(parseQte('2,5'), 2.5));
    test('point accepté', () => expect(parseQte('2.5'), 2.5));
    test('saisie invalide => null au lieu d\'une exception', () => expect(parseQte('abc'), isNull));
  });
}
