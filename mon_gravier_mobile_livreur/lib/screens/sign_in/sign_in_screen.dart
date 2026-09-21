import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../constants.dart';
import '../../globale.dart';
import 'components/sign_form.dart';

/// ÉCRAN DE CONNEXION — ESPACE LIVREUR.
///
/// Même parti pris que l'application client, pour que les trois applications se
/// ressemblent : un en-tête aux bleus du logo qui présente la marque, puis une
/// feuille claire à coins arrondis qui porte le formulaire.
///
/// Il était bâti comme une page ordinaire : une barre de titre portant une
/// phrase entière — « Connectez-vous sur l'espace livreur » — au-dessus d'un
/// logo étiré, d'un titre et d'un formulaire, le tout sur du blanc.
///
/// Rien n'est retiré : mêmes champs, même validation, même authentification, et
/// le garde-fou de sortie de l'application (`WillPopScope`) est conservé —
/// c'est l'écran de départ.
class SignInScreen extends StatelessWidget {
  static String routeName = "/sign_in";

  const SignInScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // L'écran n'a plus de barre de titre : c'est l'en-tête bleu qui passe sous
    // la barre système, et ses pictogrammes doivent donc être clairs.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: kPrimaryColor,
        body: WillPopScope(
          onWillPop: () async {
            bool backStatus = onWillPop();
            if (backStatus) {
              exit(0);
            }
            return false;
          },
          child: Column(
            children: [
              // -------------------------------------------------- EN-TÊTE
              SafeArea(
                bottom: false,
                child: Padding(
                  padding:
                      const EdgeInsets.fromLTRB(kSpaceXl, 40, kSpaceXl, 76),
                  child: Row(
                    children: [
                      Container(
                        height: 62,
                        width: 62,
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                        ),
                        padding: const EdgeInsets.all(3),
                        child: ClipOval(
                          child: Image.asset(
                            'assets/images/logo.png',
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      const SizedBox(width: kSpaceLg),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              "MON GRAVIER",
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 1.4,
                                height: 1.2,
                              ),
                            ),
                            SizedBox(height: 4),
                            Text(
                              "Espace livreur",
                              style: TextStyle(
                                color: Color(0xCCFFFFFF),
                                fontSize: 13,
                                height: 1.35,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              // ----------------------------------------------- FORMULAIRE
              Expanded(
                child: Container(
                  width: double.infinity,
                  decoration: const BoxDecoration(
                    color: kScaffoldColor,
                    borderRadius:
                        BorderRadius.vertical(top: Radius.circular(kRadiusXl)),
                  ),
                  child: SafeArea(
                    top: false,
                    child: SingleChildScrollView(
                      physics: const BouncingScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(
                          kSpaceXl, kSpaceXxl, kSpaceXl, kSpaceLg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            "Connexion",
                            textAlign: TextAlign.center,
                            style: headingStyle,
                          ),
                          const SizedBox(height: kSpaceSm),
                          const Text(
                            "Connectez-vous pour retrouver vos courses et vos "
                            "livraisons du jour.",
                            textAlign: TextAlign.center,
                            style: kCorpsSecondaireStyle,
                          ),
                          const SizedBox(height: kSpaceXl),
                          const SignForm(),
                          const SizedBox(height: kSpaceXxl),
                          // UNE SEULE version affichée. Cet écran annonçait
                          // « 1.0.5 » (kVersionApplication) pendant que l'écran
                          // Compte annonçait « 1.0.0 (1) » : deux écrans de la
                          // même application donnaient deux versions
                          // différentes, et aucune ne servait de repère.
                          Text(
                            "Version $versionApplication",
                            textAlign: TextAlign.center,
                            style: kLegendeStyle,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
