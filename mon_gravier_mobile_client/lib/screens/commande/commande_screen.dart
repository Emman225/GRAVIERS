import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/Commande.dart';
import 'package:mon_gravier_com/screens/commande/components/commande_liste_screen.dart';

import '../../helper/constants.dart';
import 'components/location_liste_screen.dart';

class CommandeScreen extends StatefulWidget {
  static String routeName = "/commande_location_liste";
  const CommandeScreen({super.key});

  @override
  State<CommandeScreen> createState() => CommandeScreenState();
}

class CommandeScreenState extends State<CommandeScreen> {
  List<DetailsCommande> commandeAttente = [];
  List<DetailsCommande> commandeEnTraitement = [];
  List<DetailsCommande> commandeTermine = [];

  List<DetailsLocation> locationAttente = [];
  List<DetailsLocation> locationEnCours = [];
  List<DetailsLocation> locationTerminee = [];
  Commande com = Commande();
  int? retour = Get.arguments;

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
              var commandes = com.data?.commande ?? [];
              commandeAttente = commandes
                  .where((c) => c.etatCommande == COMMANDE_EN_ATTENTE)
                  .toList();
              commandeEnTraitement = commandes
                  .where((c) => c.etatCommande == COMMANDE_EN_TRAITEMENT)
                  .toList();
              commandeTermine = commandes
                  .where((c) => c.etatCommande == COMMANDE_TERMINE)
                  .toList();
              pagesCommande = [
                CommandeListeScreen(commandes: commandeAttente),
                CommandeListeScreen(commandes: commandeEnTraitement),
                CommandeListeScreen(commandes: commandeTermine),
              ];

              var locations = com.data?.location ?? [];
              locationAttente = locations
                  .where((c) => c.etatLocation == LOCATION_EN_ATTENTE)
                  .toList();
              locationEnCours = locations
                  .where((c) => c.etatLocation == LOCATION_EN_COURS)
                  .toList();
              locationTerminee = locations
                  .where((c) => c.etatLocation == LOCATION_TERMINE)
                  .toList();
              pagesLocation = [
                LocationListeScreen(locations: locationAttente),
                LocationListeScreen(locations: locationEnCours),
                LocationListeScreen(locations: locationTerminee),
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
    pagesCommande = [
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

  List<Widget> pagesCommande = [];
  List<Widget> pagesLocation = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des commandes & locations"),
        automaticallyImplyLeading: afficheRetour,
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
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ContainedTabBarView(
                      tabBarProperties: TabBarProperties(
                        background: Container(
                          margin: const EdgeInsets.only(bottom: 5),
                          decoration: const BoxDecoration(
                            color: kSecondaryColor,
                            borderRadius: BorderRadius.all(Radius.circular(8.0)),
                          ),
                        ),
                        indicatorColor: kPrimaryColor,
                        labelColor: Colors.white,
                        unselectedLabelColor: Colors.black,
                      ),
                      tabs: const [
                        Text('Commande', style: white16BoldTextStyle),
                        Text('Location', style: white16BoldTextStyle),
                      ],
                      views: [
                        _listeCommandeWidget(),
                        _listeLocationWidget(),
                      ],
                      onChange: (index) {
                        if (kDebugMode) {
                          print(index);
                        }
                      }
                  ),
                ),
              ),
      ),
    );
  }

  _listeCommandeWidget(){
    return DefaultTabController(
      length: pagesCommande.length,
      child: Column(
        children: <Widget>[
          ButtonsTabBar(
            radius: 10,
            backgroundColor: greenColor,
            unselectedBackgroundColor: const Color(0xFFE5E1E1),
            unselectedLabelStyle:
            const TextStyle(color: blackColor),
            labelStyle: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.bold),
            tabs: const [
              Tab(icon: Icon(Icons.pause), text: "En Attente"),
              Tab(
                  icon: Icon(Icons.play_arrow_outlined),
                  text: "En Traitement"),
              Tab(icon: Icon(Icons.flag_outlined), text: "Terminée"),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: pagesCommande,
            ),
          ),
        ],
      ),
    );
  }

  _listeLocationWidget(){
    return DefaultTabController(
      length: pagesLocation.length,
      child: Column(
        children: <Widget>[
          ButtonsTabBar(
            radius: 10,
            backgroundColor: greenColor,
            unselectedBackgroundColor: const Color(0xFFE5E1E1),
            unselectedLabelStyle:
            const TextStyle(color: blackColor),
            labelStyle: const TextStyle(
                color: Colors.white, fontWeight: FontWeight.bold),
            tabs: const [
              Tab(icon: Icon(Icons.pause), text: "En Attente"),
              Tab(
                  icon: Icon(Icons.play_arrow_outlined),
                  text: "En Cours"),
              Tab(icon: Icon(Icons.flag_outlined), text: "Terminée"),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: pagesLocation,
            ),
          ),
        ],
      ),
    );
  }

}
