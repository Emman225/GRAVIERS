import 'package:flutter/material.dart';

import '../constants.dart';

/// UNE INFORMATION D'UNE FICHE : un libellé en clair et sa valeur.
class InfoFiche {
  final String libelle;
  final String valeur;
  final Color couleur;

  const InfoFiche(this.libelle, this.valeur, {this.couleur = kTextColor});
}

/// LA FICHE DE DÉTAIL : une carte, une ligne par information, le libellé en
/// petites capitales grises au-dessus de la valeur en gras — les deux ne se
/// confondent jamais, et aucun libellé n'est abrégé.
class FicheDetails extends StatelessWidget {
  final List<InfoFiche> lignes;

  const FicheDetails({super.key, required this.lignes});

  @override
  Widget build(BuildContext context) {
    final visibles = lignes
        .where((l) => l.valeur.trim().isNotEmpty && l.valeur.trim() != 'null')
        .toList();
    if (visibles.isEmpty) return const SizedBox.shrink();
    return Container(
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderColor),
      ),
      child: Column(
        children: [
          for (int i = 0; i < visibles.length; i++) ...[
            if (i > 0)
              const Divider(height: 1, thickness: 1, color: kBorderColor),
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: kSpaceMd, vertical: kSpaceSm + 2),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    visibles[i].libelle.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.6,
                      color: kTextMutedColor,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    visibles[i].valeur,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: visibles[i].couleur,
                      height: 1.25,
                    ),
                    softWrap: true,
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
