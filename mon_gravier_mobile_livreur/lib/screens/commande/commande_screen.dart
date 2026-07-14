import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/models/Commande.dart';
import 'package:mon_gravier_com_livreur/screens/commande/components/commande_liste_screen.dart';

import '../../helper/constants.dart';
import '../sign_in/sign_in_screen.dart';

class CommandeScreen extends StatefulWidget {
  const CommandeScreen({super.key});

  @override
  State<CommandeScreen> createState() => CommandeScreenState();
}

class CommandeScreenState extends State<CommandeScreen> {
  List<DetailsCommande> commandeAttente = [];
  List<DetailsCommande> commandeEnTraitement = [];
  List<DetailsCommande> commandeTermine = [];
  List<DetailsCommande> commandeLivre = [];
  Commande com = Commande();

  chargerCommande() async {
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
            .post(Uri.parse('${lienAPI()}liste-commande'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          com = Commande.fromJson(datas);
          if (com.code == 200) {
            setState(() {
              var commandes = com.data ?? [];
              commandeAttente = commandes
                  .where((c) => c.etatCommande == COMMANDE_EN_ATTENTE)
                  .toList();
              commandeEnTraitement = commandes
                  .where((c) => c.etatCommande == COMMANDE_EN_TRAITEMENT)
                  .toList();
              commandeTermine = commandes
                  .where((c) => c.etatCommande == COMMANDE_TERMINE)
                  .toList();
              pages = [
                CommandeListeScreen(commandes: commandeAttente),
                CommandeListeScreen(commandes: commandeEnTraitement),
                CommandeListeScreen(commandes: commandeTermine),
              ];
            });
          } else {
            EasyLoading.showError(com.message ?? '');
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
      CommandeListeScreen(commandes: commandeAttente),
      CommandeListeScreen(commandes: commandeEnTraitement),
      CommandeListeScreen(commandes: commandeTermine),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerCommande();
      }
    });
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des commandes"),
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
                          Tab(icon: Icon(Icons.flag_outlined), text: "Terminé"),
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
    );
  }
}
