import 'package:flutter/material.dart';

import '../../../constants.dart';

/// TITRE DE SECTION.
///
/// Le bouton « Voir plus » était toujours dessiné : quand `showVoirPlus` valait
/// faux, il restait un `TextButton` avec un libellé VIDE — invisible, mais
/// cliquable, et occupant une place que le titre n'avait pas. Il n'existe
/// désormais que lorsqu'il mène quelque part.
class SectionTitle extends StatelessWidget {
  const SectionTitle({
    super.key,
    required this.title,
    required this.press,
    required this.showVoirPlus,
  });

  final String title;
  final GestureTapCallback press;
  final bool showVoirPlus;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          child: Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: kTitreSectionStyle,
          ),
        ),
        // « VOIR TOUT » ETAIT INVISIBLE.
        //
        // C'etait un `TextButton` de 13 px, sans fond ni contour, dans la
        // couleur de la marque sur un fond clair : rien ne signalait qu'on
        // pouvait cliquer, et le seul chemin vers le catalogue complet depuis
        // l'accueil passait par la.
        //
        // Il devient une pastille pleine, au bleu de la marque, avec sa fleche.
        if (showVoirPlus)
          Material(
            color: kPrimaryColor,
            borderRadius: BorderRadius.circular(kRadiusPill),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: press,
              child: const Padding(
                padding: EdgeInsets.fromLTRB(kSpaceMd, 7, kSpaceSm, 7),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      "Voir tout",
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        height: 1.1,
                      ),
                    ),
                    SizedBox(width: 2),
                    Icon(Icons.arrow_forward_rounded,
                        size: 15, color: Colors.white),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
