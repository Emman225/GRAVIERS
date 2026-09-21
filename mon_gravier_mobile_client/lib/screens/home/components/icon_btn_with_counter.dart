import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com/globale.dart';

import '../../../constants.dart';

/// BOUTON PANIER, AVEC LE NOMBRE D'ARTICLES.
///
/// Le compteur est relu toutes les trois secondes : c'est le mécanisme
/// d'origine, et le remplacer supposerait de toucher à la gestion d'état du
/// panier — hors de question à la veille de la livraison.
///
/// En revanche, il appelait `setState` À CHAQUE battement, que le panier ait
/// changé ou non : l'écran entier se reconstruisait vingt fois par minute, en
/// permanence, y compris quand le client lisait simplement une fiche produit.
/// On ne redessine désormais que si le nombre a réellement bougé.
class IconBtnWithCounter extends StatefulWidget {
  const IconBtnWithCounter({
    super.key,
    required this.svgSrc,
    required this.press,
    this.couleurIcone = kTextColor,
    this.surFondSombre = false,
  });

  final String svgSrc;
  final GestureTapCallback press;
  final Color couleurIcone;

  /// Posé sur la barre de titre navy ou sur le bandeau d'accueil : la pastille
  /// devient un voile blanc et le pictogramme passe en blanc. Sans cela, un
  /// pictogramme sombre dans un cercle gris clair reste illisible sur du navy.
  final bool surFondSombre;

  @override
  State<IconBtnWithCounter> createState() => _IconBtnWithCounterState();
}

class _IconBtnWithCounterState extends State<IconBtnWithCounter> {
  Timer? timer;
  int numOfitem = 0;

  @override
  void initState() {
    super.initState();
    numOfitem = paniers.length;
    timer = Timer.periodic(const Duration(seconds: 3), (Timer t) => majQte());
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  majQte() {
    // Écran quitté entre deux battements : redessiner un état détruit lève une
    // exception qui, en version release, ressort sans origine lisible.
    if (!mounted) return;
    if (numOfitem == paniers.length) return;
    setState(() {
      numOfitem = paniers.length;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: numOfitem == 0
          ? "Mon panier, vide"
          : "Mon panier, $numOfitem article(s)",
      // LE COMPTEUR ÉTAIT COUPÉ SUR UN CÔTÉ.
      //
      // La pastille ronde était un `Material` en `clipBehavior: antiAlias` :
      // tout ce qui dépassait du cercle était ROGNÉ — or le compteur est
      // justement posé en débord, en haut à droite. Le `Clip.none` du `Stack`
      // n'y pouvait rien, le rognage se produisant un cran plus haut.
      //
      // Le Stack passe donc AU-DESSUS : le cercle rogné n'est plus qu'un de ses
      // enfants, et le compteur, posé à côté, n'est plus rogné par personne.
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Material(
            color: widget.surFondSombre ? kChipSurAppBar : kSurfaceMutedColor,
            shape: const CircleBorder(),
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: widget.press,
              // 46 px : au-dessus du minimum tactile confortable (44).
              child: SizedBox(
                height: 46,
                width: 46,
                child: Padding(
                  padding: const EdgeInsets.all(13),
                  child: SvgPicture.asset(
                    widget.svgSrc,
                    colorFilter: ColorFilter.mode(
                      widget.surFondSombre ? Colors.white : widget.couleurIcone,
                      BlendMode.srcIn,
                    ),
                  ),
                ),
              ),
            ),
          ),
          if (numOfitem != 0)
            Positioned(
              top: -3,
              right: -3,
              // Le compteur ne réagit pas au doigt : c'est le cercle qui ouvre
              // le panier. Sans cela, toucher le chiffre ne ferait rien.
              child: IgnorePointer(
                child: Container(
                  constraints: const BoxConstraints(minWidth: 21),
                  height: 21,
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(
                    color: kAccentColor,
                    borderRadius: BorderRadius.circular(kRadiusPill),
                    border: Border.all(
                      width: 2,
                      color: widget.surFondSombre
                          ? kPrimaryColor
                          : Colors.white,
                    ),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    numOfitem > 99 ? "99+" : "$numOfitem",
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 10,
                      height: 1,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
