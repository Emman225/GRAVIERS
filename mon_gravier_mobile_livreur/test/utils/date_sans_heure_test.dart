import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mon_gravier_com_livreur/globale.dart';

/// UNE DATE SANS HEURE NE S'AFFICHE PAS AVEC UNE HEURE.
///
/// « Date livraison : 21 juillet 2026 à 00h00 » : ce 00h00 n'était pas une
/// heure fausse, c'était une heure INVENTÉE. La colonne
/// `livraison.date_livraison` est de type DATE — le client choisit un jour,
/// jamais une heure — et minuit n'est que ce que produit une date sans
/// horaire. Le livreur pouvait le prendre pour un rendez-vous.
void main() {
  setUpAll(() async {
    await initializeDateFormatting('fr_FR', null);
  });

  test("une date seule s'affiche sans heure", () {
    final rendu = formaterDate('2026-07-21');

    expect(rendu, '21 juillet 2026');
    expect(rendu.contains('h'), isFalse,
        reason: "Le « 00h00 » est une heure inventée : la base n'en porte pas.");
  });

  test("une date-heure garde son heure, minuit compris", () {
    // Elle AFFIRME une heure : on ne la lui retire pas.
    expect(formaterDate('2026-07-21 14:30:00'), '21 juillet 2026 à 14h30');
    expect(formaterDate('2026-07-21 00:00:00'), '21 juillet 2026 à 00h00');
  });

  test("un motif imposé par l'appelant reste intact", () {
    expect(formaterDate('2026-07-21 14:30:00', format: 'd MMMM y'),
        '21 juillet 2026');
  });

  test("une date illisible ne casse pas l'écran", () {
    // Les appelants passent parfois « null » ou un motif de repli : une
    // exception ici remplaçait toute la carte par un rectangle rouge.
    for (final entree in ['', 'null', 'dd/MM/yyyy', 'n importe quoi']) {
      expect(formaterDate(entree), '',
          reason: "« $entree » ne doit produire aucun texte, et surtout pas "
              "d'exception.");
    }
  });
}
