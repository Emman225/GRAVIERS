import 'package:flutter/material.dart';

import '../../components/bouton_retour.dart';
import '../../components/continuer_san_compte.dart';
import '../../constants.dart';
import 'components/sign_up_form.dart';

class SignUpScreen extends StatelessWidget {
  static String routeName = "/sign_up";

  const SignUpScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const BoutonRetour(),
        title: const Text("Inscription"),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(
              kSpaceXl, kSpaceXl, kSpaceXl, kSpaceXl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Le logo était étiré dans un cadre de 200 x 100 sans
              // `BoxFit` : ses proportions dépendaient de celles du fichier.
              Center(
                child: Image.asset(
                  'assets/images/logo.png',
                  height: 64,
                  fit: BoxFit.contain,
                ),
              ),
              const SizedBox(height: kSpaceXxl),
              const Text("Créer votre compte", style: headingStyle),
              const SizedBox(height: kSpaceSm),
              const Text(
                "Quelques informations suffisent pour commander et suivre "
                "vos livraisons.",
                style: kCorpsSecondaireStyle,
              ),
              const SizedBox(height: kSpaceXl),
              const SignUpForm(),
              const SizedBox(height: kSpaceXl),
              const Text(
                "En continuant, vous confirmez avoir accepté nos termes et conditions.",
                textAlign: TextAlign.center,
                style: kLegendeStyle,
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: const ContinuerSansCompteWidget(),
    );
  }
}
