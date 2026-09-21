import 'package:flutter/material.dart';

import '../../../constants.dart';

/// VIGNETTE DE L'ESPACE PERSONNEL.
///
/// Ces deux applications n'affichent PAS de photo de profil — le code qui la
/// téléchargeait est commenté depuis l'origine — mais le logo. Ce choix est
/// conservé ; seul le cadre change, pour rejoindre celui de l'application
/// client : un disque net, sur fond clair, avec un contour.
class ProfilePic extends StatelessWidget {
  const ProfilePic({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 104,
      width: 104,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: kSurfaceColor,
        border: Border.all(color: kBorderColor, width: 2),
      ),
      padding: const EdgeInsets.all(kSpaceSm),
      child: ClipOval(
        child: Image.asset('assets/images/logo.png', fit: BoxFit.cover),
      ),
    );
  }
}
