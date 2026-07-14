import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/models/User.dart';
import 'package:mon_gravier_com/screens/commande/commande_screen.dart';
import 'package:mon_gravier_com/screens/edit_profil/edit_profile_screen.dart';
import 'package:mon_gravier_com/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com/screens/paiement/paiement_screen.dart';
import 'package:mon_gravier_com/screens/sign_in/sign_in_screen.dart';
import 'package:mon_gravier_com/screens/souhait/souhait_screen.dart';
import 'package:http/http.dart' as http;

import '../../components/empty_user_widget.dart';
import '../../globale.dart';
import '../../helper/constants.dart';
import '../client_a_terme/client_a_terme_screen.dart';
import '../devis/devis_screen.dart';
import '../facture/facture_screen.dart';
import '../init_screen.dart';
import 'components/profile_menu.dart';
import 'components/profile_pic.dart';

class ProfileScreen extends StatelessWidget {
  static String routeName = "/profile";

  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Mon espace"),
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
            onPressed: () => Get.toNamed(EditProfileScreen.routeName),
            icon: const Icon(Icons.person, color: redColor, size: 30),
          ),
          const SizedBox(width: 10),
        ],
      ),
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
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
                      if(user.clientATerme == false) ...[
                        ProfileMenu(
                          text: "Devenir client à terme",
                          icon: "assets/icons/home.svg",
                          press: () => Get.toNamed(DemandeClientATermeScreen.routeName),
                        ),
                      ],
                      ProfileMenu(
                        text: "Liste des commandes & locations",
                        icon: "assets/icons/commande.svg",
                        press: () {
                          afficheRetour = true;
                          Get.toNamed(CommandeScreen.routeName);
                        },
                      ),
                      ProfileMenu(
                        text: "Liste des devis",
                        icon: "assets/icons/devis.svg",
                        press: () => Get.toNamed(DevisScreen.routeName),
                      ),
                      ProfileMenu(
                        text: "Paiements",
                        icon: "assets/icons/Cash.svg",
                        press: () => Get.toNamed(PaiementScreen.routeName),
                      ),
                      ProfileMenu(
                        text: "Factures",
                        icon: "assets/icons/facture.svg",
                        press: () => Get.toNamed(FactureScreen.routeName),
                      ),
                      ProfileMenu(
                        text: "Liste de souhait",
                        icon: "assets/icons/Heart Icon.svg",
                        press: () => Get.toNamed(SouhaitScreen.routeName),
                      ),
                      ProfileMenu(
                        text: "Modifier mot de passe",
                        icon: "assets/icons/Settings.svg",
                        press: () => Get.toNamed(ModifierPasseScreen.routeName, arguments: 1),
                      ),
                      ProfileMenu(
                        text: "Supprimer mon compte",
                        icon: "assets/icons/Trash.svg",
                        press: () async {
                          if (await confirmationAction(context, "Attention", "Voulez-vous supprimer votre compte ?")) {
                            _supprimerCompte();
                          }
                        },
                      ),
                      ProfileMenu(
                        text: "Déconnexion",
                        icon: "assets/icons/Log out.svg",
                        press: () => _deconnexion(),
                      ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }

  _deconnexion(){
    lireOuEcrireDonnee("token", '', 1);
    lireOuEcrireDonnee("type", '', 1);
    user = User();
    Get.offAllNamed(SignInScreen.routeName);
  }
  _supprimerCompte() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}suppression-compte-client'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            _deconnexion();
            Get.offAllNamed(SignInScreen.routeName);
          } else {
            EasyLoading.showError(datas['message'] ?? '');
          }
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
