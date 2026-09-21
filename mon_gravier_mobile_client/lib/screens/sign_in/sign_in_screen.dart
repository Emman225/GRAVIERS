import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../components/no_account_text.dart';
import '../../constants.dart';
import '../../globale.dart';
import '../init_screen.dart';
import 'components/sign_form.dart';

/// ÉCRAN DE CONNEXION.
///
/// Il était bâti comme une page ordinaire : une barre de titre « Connexion »
/// au-dessus d'un logo, d'un titre, d'un formulaire et d'un bouton de sortie —
/// cinq blocs empilés sur du blanc, sans hiérarchie. Or c'est le PREMIER écran
/// de l'application : c'est là que la marque doit se présenter.
///
/// Nouveau parti pris, sans barre de titre :
///
///  · un en-tête aux bleus du logo occupe le haut de l'écran et porte le logo,
///    le nom et la promesse — ce que l'application est, avant ce qu'elle
///    demande ;
///  · le formulaire vit dans une feuille blanche à coins arrondis, posée
///    par-dessus : c'est elle qui appelle l'action ;
///  · « Parcourir le catalogue sans compte » reste accessible, en bas, en
///     action secondaire.
///
/// Rien n'est retiré : mêmes champs, même validation, même authentification,
/// mêmes chemins vers l'inscription et le mot de passe oublié.
class SignInScreen extends StatelessWidget {
  static String routeName = "/sign_in";

  const SignInScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // L'écran n'a plus de barre de titre : c'est l'en-tête bleu qui passe sous
    // la barre système, et les pictogrammes de celle-ci doivent donc être
    // clairs.
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: kPrimaryColor,
        body: Column(
          children: [
            // ---------------------------------------------------- EN-TÊTE
            SafeArea(
              bottom: false,
              child: Padding(
                // Espace bleu largement ouvert : c'est le premier écran de
                // l'application, et le seul endroit où la marque se présente
                // avant de demander quoi que ce soit. Le formulaire, lui,
                // défile — lui laisser moins de hauteur ne coûte rien.
                padding: const EdgeInsets.fromLTRB(
                    kSpaceXl, 40, kSpaceXl, 76),
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
                            "Sable, gravier et matériel de chantier, "
                            "livrés chez vous.",
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

            // ------------------------------------------------- FORMULAIRE
            Expanded(
              child: Container(
                width: double.infinity,
                decoration: const BoxDecoration(
                  color: kScaffoldColor,
                  // Arrondi franc : la feuille se lit comme un panneau qui
                  // remonte sur le bleu, et non comme un simple changement de
                  // couleur en travers de l'écran.
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
                          "Connectez-vous pour retrouver vos commandes, vos "
                          "livraisons et vos factures.",
                          textAlign: TextAlign.center,
                          style: kCorpsSecondaireStyle,
                        ),
                        const SizedBox(height: kSpaceXl),
                        const SignForm(),
                        const SizedBox(height: kSpaceXl),
                        const NoAccountText(),
                        const SizedBox(height: kSpaceLg),

                        // Séparateur : ce qui suit n'est plus la connexion.
                        Row(
                          children: [
                            const Expanded(
                                child: Divider(height: 1, color: kBorderColor)),
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: kSpaceMd),
                              child: Text("ou",
                                  style: kLegendeStyle.copyWith(fontSize: 12)),
                            ),
                            const Expanded(
                                child: Divider(height: 1, color: kBorderColor)),
                          ],
                        ),
                        const SizedBox(height: kSpaceLg),

                        // Sortie de secours : accessible, mais en action
                        // secondaire. Elle était auparavant un bouton vert
                        // plein, donc plus voyante que « Je me connecte ».
                        OutlinedButton.icon(
                          onPressed: () => Get.toNamed(InitScreen.routeName),
                          icon: const Icon(Icons.storefront_outlined, size: 18),
                          label: const Text("Parcourir sans compte"),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size(double.infinity, 50),
                            foregroundColor: kPrimaryColor,
                            side: const BorderSide(
                                color: kBorderFortColor, width: 1.4),
                          ),
                        ),
                        const SizedBox(height: kSpaceXl),
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
    );
  }
}
