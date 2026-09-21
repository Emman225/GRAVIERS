import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/init_screen.dart';

import '../../constants.dart';

/// ÉCHEC D'UNE COMMANDE OU D'UN PAIEMENT.
///
/// Le titre était « Ooops » et le message s'affichait en 30 px gras noir dans
/// une colonne non défilante : sur un échec de paiement en ligne, le client
/// recevait une interjection en guise d'explication, et un texte un peu long
/// faisait déborder l'écran.
///
/// Le message vient toujours de `Get.arguments` et n'est pas modifié.
class CommandeErrorScreen extends StatefulWidget {
  static String routeName = "/commande_error";

  const CommandeErrorScreen({super.key});

  @override
  State<CommandeErrorScreen> createState() => _CommandeErrorScreenState();
}

class _CommandeErrorScreenState extends State<CommandeErrorScreen> {
  String message = '';

  @override
  void initState() {
    message = Get.arguments;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const SizedBox(),
        title: const Text("Commande non aboutie"),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.symmetric(
                    horizontal: kSpaceXl, vertical: kSpaceXxl),
                child: Column(
                  children: [
                    Container(
                      width: 96,
                      height: 96,
                      decoration: const BoxDecoration(
                        color: kErrorSoftColor,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.priority_high_rounded,
                          size: 46, color: kErrorColor),
                    ),
                    const SizedBox(height: kSpaceXl),
                    const Text(
                      "La commande n'a pas abouti",
                      textAlign: TextAlign.center,
                      style: headingStyle,
                    ),
                    const SizedBox(height: kSpaceMd),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 340),
                      child: Text(
                        message,
                        textAlign: TextAlign.center,
                        style: kCorpsStyle.copyWith(
                          fontSize: 15,
                          color: kTextSecondaryColor,
                        ),
                      ),
                    ),
                    const SizedBox(height: kSpaceLg),
                    const Text(
                      "Votre panier est conservé : vous pouvez reprendre la "
                      "commande depuis l'accueil.",
                      textAlign: TextAlign.center,
                      style: kLegendeStyle,
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding:
                  const EdgeInsets.fromLTRB(kSpaceXl, 0, kSpaceXl, kSpaceXl),
              child: ElevatedButton(
                onPressed: () => Get.offAllNamed(InitScreen.routeName),
                child: const Text("Retour à l'accueil"),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
