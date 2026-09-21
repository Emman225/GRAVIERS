import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../../constants.dart';

/// LIGNE DE MENU DE L'ESPACE CLIENT.
///
/// Chaque entrée était un bouton gris de 20 px de marge intérieure, séparé du
/// suivant par 20 px de vide : neuf pavés flottants, sans regroupement, qui
/// occupaient deux écrans et demi. Le chevron était un `arrow_forward_ios` à
/// taille par défaut, plus lourd que le libellé qu'il accompagnait — et sa
/// couleur venait de la propriété `color` de SvgPicture, dépréciée.
///
/// Les lignes forment maintenant une liste : pictogramme cadré, libellé
/// lisible, chevron discret.
class ProfileMenu extends StatelessWidget {
  const ProfileMenu({
    super.key,
    required this.text,
    required this.icon,
    this.press,
    this.destructif = false,
  });

  final String text, icon;
  final VoidCallback? press;

  /// Suppression de compte, déconnexion : signalées, jamais mises en avant.
  final bool destructif;

  @override
  Widget build(BuildContext context) {
    final Color teinte = destructif ? kErrorColor : kPrimaryColor;

    return Padding(
      padding:
          const EdgeInsets.symmetric(horizontal: kSpaceXl, vertical: kSpaceXs),
      child: Material(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        child: InkWell(
          onTap: press,
          borderRadius: BorderRadius.circular(kRadiusMd),
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(kRadiusMd),
              border: Border.all(color: kBorderColor),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: kSpaceLg, vertical: kSpaceMd),
              child: Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: destructif ? kErrorSoftColor : kPrimarySoftColor,
                      borderRadius: BorderRadius.circular(kRadiusSm),
                    ),
                    alignment: Alignment.center,
                    child: SvgPicture.asset(
                      icon,
                      width: 18,
                      height: 18,
                      colorFilter: ColorFilter.mode(teinte, BlendMode.srcIn),
                    ),
                  ),
                  const SizedBox(width: kSpaceMd),
                  Expanded(
                    child: Text(
                      text,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: destructif ? kErrorColor : kTextColor,
                        height: 1.3,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right,
                      size: 20, color: kTextMutedColor),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
