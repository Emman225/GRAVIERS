import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/User.dart';
import 'package:mon_gravier_com_livreur/screens/demande_retrait/liste_demande_retrait_screen.dart';
import 'package:mon_gravier_com_livreur/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com_livreur/screens/sign_in/sign_in_screen.dart';

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
        title: const Text("Mon compte"),
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: Container(
                width: double.infinity,
                height: heightOfScreen(context),
                decoration: const BoxDecoration(
                  image: DecorationImage(
                    image: AssetImage("assets/images/bg.jpg"),
                    fit: BoxFit.cover,
                    opacity: 0.1,
                  ),
                ),
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Column(
                    children: [
                      const ProfilePic(),
                      Text(user.nom.toString().toUpperCase(),
                          textAlign: TextAlign.center),
                      const SizedBox(height: 20),
                      ProfileMenu(
                        text: "Demander Retrait",
                        icon: "assets/icons/Cash.svg",
                        press: () => Get.toNamed(ListeDemandeRetraitScreen.routeName),
                      ),
                      ProfileMenu(
                        text: "Modifier mot de passe",
                        icon: "assets/icons/Settings.svg",
                        press: () => Get.toNamed(ModifierPasseScreen.routeName, arguments: 1),
                      ),
                      ProfileMenu(
                        text: "Déconnexion",
                        icon: "assets/icons/Log out.svg",
                        press: () {
                          lireOuEcrireDonnee("token", '', 1);
                          lireOuEcrireDonnee("type", '', 1);
                          user = User();
                          Get.offAllNamed(SignInScreen.routeName);
                        },
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}
