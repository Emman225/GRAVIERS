import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/globale.dart';

/// LE MOBILE ET LE SERVEUR DOIVENT RETENIR LE MÊME NOMBRE DE POINTS.
///
/// Les points pouvaient couvrir une commande ENTIÈRE : sans livraison, le total
/// tombait à zéro et la commande devenait une impasse — la passerelle appelée
/// avec 0, l'encaissement au guichet refusant le montant nul (`min:1`), le
/// virement supposant un justificatif de 0. Les points, eux, étaient déjà
/// débités.
///
/// Le serveur applique désormais un plancher (`Help::pointsUtilisables`).
/// L'application applique EXACTEMENT la même règle : si elle en retenait
/// davantage, elle afficherait un total que le serveur ne retiendrait pas —
/// l'écran annoncerait 0 F pendant que la commande partirait à 1 000 F. C'est
/// précisément le genre d'écart qui a déjà coûté un règlement impossible à
/// imputer sur la commande 849677.
///
/// Les valeurs attendues ci-dessous sont celles vérifiées côté serveur dans
/// `graviers/tests/Feature/PlancherDePaiementTest.php`.
void main() {
  setUp(() {
    montantMinimumAPayer = 1000;
  });

  tearDown(() {
    montantMinimumAPayer = 0;
  });

  double retenus({
    double ht = 22000,
    double promo = 0,
    double tva = 18,
    double livraison = 0,
    double valeur = 10,
    double demandes = 5000,
    double solde = 5000,
  }) =>
      pointsUtilisables(
        ht: ht,
        remisePromo: promo,
        tauxTva: tva,
        livraison: livraison,
        valeurPoint: valeur,
        demandes: demandes,
        solde: solde,
      );

  test('le total ne tombe jamais à zéro', () {
    final points = retenus();
    final total = (22000 - points * 10) * 1.18;

    expect(total.round(), greaterThanOrEqualTo(1000),
        reason: 'À zéro, la commande ne peut être réglée par AUCUN moyen.');
    expect(points, lessThan(5000));
  });

  test('la coupure est serrée : un point de plus franchirait le plancher', () {
    final points = retenus();

    expect(((22000 - points * 10) * 1.18).round(), greaterThanOrEqualTo(1000));
    expect(((22000 - (points + 1) * 10) * 1.18).round(), lessThan(1000),
        reason: 'On ne doit pas brider plus que nécessaire : le client garde '
            'toute la remise possible.');
  });

  test('une livraison suffisante libère tous les points', () {
    // Même cas que l'essai serveur : la livraison de 4 000 F reste due, donc
    // les points peuvent couvrir toute la marchandise.
    expect(retenus(livraison: 4000), 2200.0);
  });

  test('le code promo passe avant les points', () {
    final points = retenus(promo: 20000);
    final resteHt = 22000 - 20000 - points * 10;

    expect((resteHt * 1.18).round(), greaterThanOrEqualTo(1000),
        reason: 'La remise promo est un engagement déjà pris ; ce sont les '
            'points qui cèdent.');
  });

  test('le solde du client reste la limite absolue', () {
    montantMinimumAPayer = 0;
    expect(retenus(ht: 500000, solde: 12), 12.0);
  });

  test('un plancher à zéro retire la limite', () {
    montantMinimumAPayer = 0;
    expect(retenus(), 5000.0);
  });

  test('un point sans valeur n\'est pas consommé', () {
    expect(retenus(valeur: 0), 0.0);
  });

  test('un plancher non transmis ne bride rien', () {
    // Serveur plus ancien, ou champ absent de la réponse : on retombe sur
    // l'ancien comportement plutôt que d'inventer une limite côté application.
    montantMinimumAPayer = 0;
    expect(retenus(), 5000.0);
  });
}
