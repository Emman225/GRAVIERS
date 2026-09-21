import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';

/// LE LIVREUR NE DOIT JAMAIS LIRE « null » À LA PLACE D'UN LIEU.
///
/// Constaté le 29/08/2026 sur une location fraîchement traitée : l'écran
/// affichait « Lieu: null ». `adresse` vient de `adresse_livraison.affichage`
/// et reste vide quand la course a été créée sans adresse de livraison.
///
/// La cause a été corrigée côté serveur, mais les courses déjà enregistrées
/// gardent leur adresse vide : l'écran doit rester lisible pour elles.
void main() {
  UneLivraison depuis(Map<String, dynamic> json) => UneLivraison.fromJson(json);

  test('l’adresse renseignée est affichée telle quelle', () {
    expect(depuis({'adresse': 'Cocody Angré, 7e tranche'}).lieuAffiche,
        'Cocody Angré, 7e tranche');
  });

  test('sans adresse, le complément prend le relais', () {
    expect(
      depuis({'adresse': null, 'complement_adresse': 'Face à la pharmacie'})
          .lieuAffiche,
      'Face à la pharmacie',
    );
  });

  test('sans rien, le livreur lit une phrase, pas « null »', () {
    final String lieu = depuis({}).lieuAffiche;

    expect(lieu.toLowerCase(), isNot(contains('null')));
    expect(lieu, contains('non renseignée'));
  });

  test('une adresse vide ou remplie du mot « null » ne passe pas', () {
    expect(depuis({'adresse': '   '}).lieuAffiche, contains('non renseignée'));
    expect(depuis({'adresse': 'null'}).lieuAffiche, contains('non renseignée'));
  });
}
