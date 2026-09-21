import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/User.dart';
import 'package:mon_gravier_com_livreur/screens/demande_retrait/liste_demande_retrait_screen.dart';
import 'package:mon_gravier_com_livreur/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com_livreur/screens/sign_in/sign_in_screen.dart';

import '../../constants.dart';
import '../../../components/bouton_retour.dart';
import '../../globale.dart';
import '../../helper/constants.dart';
import 'components/profile_menu.dart';
import 'components/profile_pic.dart';

class ProfileScreen extends StatelessWidget {
  static String routeName = "/profile";

  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        // Onglet de la barre du bas : rien à dépiler, le retour
        // ramène à l'accueil.
        leading: BoutonRetour(
          onTap: retourAccueil,
          tooltip: "Retour à l'accueil",
        ),
        title: const Text("Mon compte"),
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Column(
                    children: [
                      // L'identité du livreur devient une CARTE : elle était
                      // posée nue au centre d'une page blanche, le nom dans la
                      // taille du corps de texte.
                      Container(
                        margin: const EdgeInsets.symmetric(
                            horizontal: kSpaceXl, vertical: kSpaceSm),
                        padding: const EdgeInsets.all(kSpaceLg),
                        decoration: BoxDecoration(
                          color: kSurfaceColor,
                          borderRadius: BorderRadius.circular(kRadiusMd),
                          border: Border.all(color: kBorderColor),
                        ),
                        child: Column(
                          children: [
                            const ProfilePic(),
                            const SizedBox(height: kSpaceMd),
                            Text(
                              user.nom.toString().toUpperCase(),
                              textAlign: TextAlign.center,
                              style: kTitreSectionStyle,
                            ),
                          ],
                        ),
                      ),
                      const _TitreGroupe("Mon activité"),
                      ProfileMenu(
                        text: "Demander un retrait",
                        icon: "assets/icons/Cash.svg",
                        press: () => Get.toNamed(ListeDemandeRetraitScreen.routeName),
                      ),
                      const _TitreGroupe("Mon compte"),
                      ProfileMenu(
                        text: "Modifier mon mot de passe",
                        icon: "assets/icons/Settings.svg",
                        press: () => Get.toNamed(ModifierPasseScreen.routeName, arguments: 1),
                      ),
                      ProfileMenu(
                        text: "Me déconnecter",
                        destructif: true,
                        icon: "assets/icons/Log out.svg",
                        press: () {
                          lireOuEcrireDonnee("token", '', 1);
                          lireOuEcrireDonnee("type", '', 1);
                          user = User();
                          Get.offAllNamed(SignInScreen.routeName);
                        },
                      ),
                      // Version affichée : sans repère visible, deux APK
                      // successifs sont indiscernables une fois installés.
                      const Padding(
                        padding: EdgeInsets.only(top: 24, bottom: 12),
                        child: Text(
                          "MON GRAVIER — version $versionApplication",
                          textAlign: TextAlign.center,
                          style: kLegendeStyle,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}

/// Intitulé de groupe : sépare l'activité du livreur des réglages de son
/// compte. La page empilait auparavant tous les boutons sans distinction.
class _TitreGroupe extends StatelessWidget {
  const _TitreGroupe(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding:
          const EdgeInsets.fromLTRB(kSpaceXl, kSpaceXl, kSpaceXl, kSpaceSm),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(
          texte.toUpperCase(),
          style: kEtiquetteStyle.copyWith(color: kTextMutedColor),
        ),
      ),
    );
  }
}
