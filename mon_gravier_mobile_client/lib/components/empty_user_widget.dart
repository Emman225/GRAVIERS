import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/sign_up/sign_up_screen.dart';

import '../constants.dart';
import '../screens/sign_in/sign_in_screen.dart';

/// ÉCRAN RÉSERVÉ AUX CLIENTS CONNECTÉS.
///
/// Le message s'affichait en ROUGE, comme une erreur, sous une photographie
/// pleine largeur — alors que le client n'a rien fait de mal : il n'est
/// simplement pas connecté. Les deux boutons étaient d'un bleu vif absent du
/// reste de l'application, et de même poids : rien ne disait lequel choisir.
///
/// Désormais : un ton neutre, une action principale (se connecter) et une
/// action secondaire (créer un compte).
class EmptyUserWidget extends StatelessWidget {
  final bool showImage;
  final String msg;

  const EmptyUserWidget({
    super.key,
    this.showImage = true,
    this.msg =
        "Connectez-vous pour retrouver vos commandes, vos livraisons et vos factures.",
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(
            horizontal: kSpaceXl, vertical: kSpaceXxl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (showImage)
              Container(
                width: 84,
                height: 84,
                decoration: const BoxDecoration(
                  color: kPrimarySoftColor,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.lock_outline,
                    size: 34, color: kPrimaryColor),
              ),
            const SizedBox(height: kSpaceXl),
            const Text(
              "Espace personnel",
              textAlign: TextAlign.center,
              style: kTitreSectionStyle,
            ),
            const SizedBox(height: kSpaceSm),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 320),
              child: Text(
                msg,
                textAlign: TextAlign.center,
                style: kCorpsSecondaireStyle,
              ),
            ),
            const SizedBox(height: kSpaceXl),
            SizedBox(
              width: 280,
              child: ElevatedButton(
                onPressed: () => Get.toNamed(SignInScreen.routeName),
                child: const Text("Je me connecte"),
              ),
            ),
            const SizedBox(height: kSpaceMd),
            SizedBox(
              width: 280,
              child: OutlinedButton(
                onPressed: () => Get.toNamed(SignUpScreen.routeName),
                child: const Text("Créer un compte"),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
