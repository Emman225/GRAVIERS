import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/init_screen.dart';

import '../../constants.dart';

/// CONFIRMATION DE COMMANDE.
///
/// Le message du serveur s'affichait en 30 px gras noir, sous une image occupant
/// 40 % de la hauteur, dans une colonne NON défilante encadrée de deux `Spacer`.
/// Un message de deux phrases faisait donc déborder l'écran — et le dernier
/// écran d'un parcours de commande est le pire endroit pour cela.
///
/// Le message arrive toujours par `Get.arguments` et n'est pas modifié.
class CommandeSuccessScreen extends StatefulWidget {
  static String routeName = "/commande_success";

  const CommandeSuccessScreen({super.key});

  @override
  State<CommandeSuccessScreen> createState() => _CommandeSuccessScreenState();
}

class _CommandeSuccessScreenState extends State<CommandeSuccessScreen> {
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
        title: const Text("Commande enregistrée"),
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
                        color: kSuccessSoftColor,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.check_rounded,
                          size: 48, color: kSuccessColor),
                    ),
                    const SizedBox(height: kSpaceXl),
                    const Text(
                      "C'est enregistré",
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
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                  kSpaceXl, 0, kSpaceXl, kSpaceXl),
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
