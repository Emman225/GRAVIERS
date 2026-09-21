import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/material.dart';

import '../constants.dart';

/// BARRE D'ONGLETS « VENTE / LOCATION », PARTAGÉE.
///
/// Cinq écrans dessinaient la leur, chacun à sa façon : fond gris uni, libellés
/// forcés en BLANC pour les deux états — sélectionné comme non sélectionné —
/// et un simple trait de 2 px comme seul indicateur. Sur le fond gris, l'onglet
/// actif et l'onglet inactif avaient donc exactement la même apparence.
///
/// Une seule définition ici : un sélecteur segmenté, la pastille blanche
/// marquant sans ambiguïté l'onglet ouvert.
TabBarProperties ongletsSegmentes() {
  return TabBarProperties(
    height: 48,
    margin: const EdgeInsets.only(bottom: kSpaceLg),
    padding: const EdgeInsets.all(4),
    background: Container(
      decoration: BoxDecoration(
        color: kSurfaceMutedColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
      ),
    ),
    indicator: BoxDecoration(
      color: kSurfaceColor,
      borderRadius: BorderRadius.circular(kRadiusSm),
      boxShadow: kShadowSoft,
    ),
    indicatorSize: TabBarIndicatorSize.tab,
    labelColor: kPrimaryColor,
    unselectedLabelColor: kTextSecondaryColor,
    labelStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
    unselectedLabelStyle:
        const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
  );
}
