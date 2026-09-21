import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';

import '../../../constants.dart';

/// PAGE D'ACCUEIL AU PREMIER LANCEMENT.
///
/// L'illustration était figée à 265 x 235 px sous un titre de 32 px : sur un
/// téléphone à petit écran, le bas de l'image sortait du cadre et Flutter
/// barrait la page. Le titre « MON GRAVIER » écrasait par ailleurs la phrase
/// qu'il surmontait, laquelle n'avait aucun style.
///
/// L'illustration occupe maintenant la place disponible, quelle qu'elle soit.
class SplashContent extends StatelessWidget {
  const SplashContent({
    super.key,
    this.text,
    this.image,
  });

  final String? text, image;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
      child: Column(
        children: <Widget>[
          const SizedBox(height: kSpaceXl),
          const Text(
            "MON GRAVIER",
            style: TextStyle(
              fontSize: 24,
              color: kPrimaryColor,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.5,
            ),
          ),
          const SizedBox(height: kSpaceMd),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 320),
            child: Text(
              text ?? '',
              textAlign: TextAlign.center,
              style: kCorpsStyle.copyWith(
                fontSize: 15,
                color: kTextSecondaryColor,
              ),
            ),
          ),
          const SizedBox(height: kSpaceXl),
          Expanded(
            child: SvgPicture.asset(
              image ?? '',
              fit: BoxFit.contain,
            ),
          ),
        ],
      ),
    );
  }
}
