import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../constants.dart';
import '../screens/init_screen.dart';

/// « Continuer sans compte », sous le formulaire de connexion.
///
/// Le bouton était VERT PLEIN, donc plus voyant que « Je me connecte » : la
/// sortie de secours attirait davantage l'attention que l'action attendue. Il
/// devient une action secondaire — toujours accessible, mais à sa place.
class ContinuerSansCompteWidget extends StatelessWidget {
  const ContinuerSansCompteWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(kSpaceXl, 0, kSpaceXl, kSpaceMd),
      child: SizedBox(
        width: double.infinity,
        child: TextButton.icon(
          onPressed: () => Get.toNamed(InitScreen.routeName),
          icon: const Icon(Icons.storefront_outlined, size: 18),
          label: const Text("Parcourir le catalogue sans compte"),
          style: TextButton.styleFrom(
            foregroundColor: kTextSecondaryColor,
            minimumSize: const Size(double.infinity, 48),
          ),
        ),
      ),
    );
  }
}
