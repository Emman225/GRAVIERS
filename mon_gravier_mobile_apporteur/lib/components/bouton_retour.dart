import 'package:flutter/material.dart';

import '../constants.dart';

/// BOUTON RETOUR DE LA BARRE DE TITRE.
///
/// Il était recopié à l'identique dans vingt-quatre écrans — un `ElevatedButton`
/// circulaire enveloppé d'un `Padding`, soit une dizaine de lignes chaque fois —
/// pendant que huit autres écrans gardaient la flèche par défaut de Material.
/// Deux boutons retour différents cohabitaient donc, sans règle.
///
/// Une seule définition ici : la même pastille partout, et la modifier revient
/// désormais à modifier un seul endroit.
class BoutonRetour extends StatelessWidget {
  const BoutonRetour({super.key, this.onTap, this.tooltip = "Retour"});

  /// Par défaut, on dépile la route courante. Certains écrans ont besoin d'un
  /// retour particulier — le panier repart à l'accueil, par exemple.
  final VoidCallback? onTap;

  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(kSpaceSm),
      child: Semantics(
        button: true,
        label: tooltip,
        child: Material(
          color: kChipSurAppBar,
          shape: const CircleBorder(),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: onTap ?? () => Navigator.maybePop(context),
            child: const SizedBox(
              // 40 px : la cible tactile confortable, sans écraser le titre.
              height: 40,
              width: 40,
              child: Icon(
                Icons.arrow_back_ios_new,
                color: Colors.white,
                size: 18,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
