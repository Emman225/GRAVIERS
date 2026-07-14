import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:hawk_fab_menu/hawk_fab_menu.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';
import 'package:mon_gravier_com/screens/demande_livraison/demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/liste_demande_livraison/liste_demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/livraison/components/livraison_liste_screen.dart';

import '../../components/empty_user_widget.dart';
import '../../helper/constants.dart';

class LivraisonScreen extends StatefulWidget {
  const LivraisonScreen({super.key});

  @override
  State<LivraisonScreen> createState() => LivraisonScreenState();
}

class LivraisonScreenState extends State<LivraisonScreen> {
  List<UneLivraison> livraisonAttente = [];
  List<UneLivraison> livraisonEnTraitement = [];
  List<UneLivraison> livraisonEffectue = [];
  HawkFabMenuController hawkFabMenuController = HawkFabMenuController();
  RetourLivraison liv = RetourLivraison();

  chargerLivraison() async {
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
            .post(Uri.parse('${lienAPI()}liste-livraison'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          liv = RetourLivraison.fromJson(datas);
          if (liv.code == 200) {
            setState(() {
              var livraisons = liv.data ?? [];
              if (kDebugMode) {
                print("taille------------------${livraisons.length}");
              }
              livraisonAttente = livraisons
                  .where((c) => c.etatLivraison == LIVRAISON_EN_ATTENTE)
                  .toList();
              livraisonEnTraitement = livraisons
                  .where((c) => c.etatLivraison == LIVRAISON_EN_TRAITEMENT)
                  .toList();
              livraisonEffectue = livraisons
                  .where((c) => c.etatLivraison == LIVRAISON_LIVREE)
                  .toList();
              pages = [
                LivraisonListeScreen(livraisons: livraisonAttente),
                LivraisonListeScreen(livraisons: livraisonEnTraitement),
                LivraisonListeScreen(livraisons: livraisonEffectue),
              ];
            });
          } else {
            EasyLoading.showError(liv.message ?? '');
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

  @override
  void initState() {
    pages = [
      LivraisonListeScreen(livraisons: livraisonAttente),
      LivraisonListeScreen(livraisons: livraisonEnTraitement),
      LivraisonListeScreen(livraisons: livraisonEffectue),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if(user.token != null && user.token != ""){
        chargerLivraison();
      }
    });
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des livraisons"),
        automaticallyImplyLeading: false,
      ),
      // floatingActionButton: FloatingActionButton.extended(
      //   backgroundColor: const Color(0xff03dac6),
      //   foregroundColor: Colors.black,
      //   onPressed: () => Get.toNamed(DemandeLivraisonScreen.routeName),
      //   icon: const Icon(Icons.add),
      //   label: const Text('Demander livraison'),
      // ),
      body: (user.token == null || user.token == "")
          ? const EmptyUserWidget()
          : HawkFabMenu(
              icon: AnimatedIcons.menu_arrow,
              fabColor: const Color(0xff03dac6),
              iconColor: whiteColor,
              hawkFabMenuController: hawkFabMenuController,
              items: [
                HawkFabMenuItem(
                  label: 'Liste demande de livraison',
                  ontap: () =>
                      Get.toNamed(ListeDemandeLivraisonScreen.routeName),
                  icon: const Icon(Icons.list_alt_rounded),
                  color: Colors.red,
                  labelColor: Colors.blue,
                ),
                HawkFabMenuItem(
                  label: 'Nouvelle demande de livraison',
                  ontap: () => Get.toNamed(DemandeLivraisonScreen.routeName),
                  icon: const Icon(Icons.add),
                  labelColor: Colors.white,
                  labelBackgroundColor: Colors.blue,
                ),
              ],
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
                  child: DefaultTabController(
                    length: pages.length,
                    child: Column(
                      children: <Widget>[
                        ButtonsTabBar(
                          radius: 10,
                          backgroundColor: kPrimaryColor,
                          unselectedBackgroundColor: kSecondaryColor,
                          unselectedLabelStyle:
                              const TextStyle(color: whiteColor),
                          labelStyle: const TextStyle(
                              color: Colors.white, fontWeight: FontWeight.bold),
                          tabs: const [
                            Tab(icon: Icon(Icons.pause), text: "En Attente"),
                            Tab(
                                icon: Icon(Icons.play_arrow_outlined),
                                text: "En Traitement"),
                            Tab(
                                icon: Icon(Icons.flag_outlined),
                                text: "Effectuée"),
                          ],
                        ),
                        Expanded(
                          child: TabBarView(
                            children: pages,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
    );
  }
}
