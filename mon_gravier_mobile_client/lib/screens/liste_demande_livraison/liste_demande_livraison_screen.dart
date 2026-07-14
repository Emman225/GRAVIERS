import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_liste_demande_livraison.dart';
import 'package:mon_gravier_com/screens/demande_livraison/demande_livraison_screen.dart';

import '../../helper/constants.dart';
import 'components/liste_demande_livraison.dart';

class ListeDemandeLivraisonScreen extends StatefulWidget {
  const ListeDemandeLivraisonScreen({super.key});
  static String routeName = "/listeDemande";

  @override
  State<ListeDemandeLivraisonScreen> createState() => ListeDemandeLivraisonScreenState();
}

class ListeDemandeLivraisonScreenState extends State<ListeDemandeLivraisonScreen> {

  List<DataListeDemandeLivraison> demandeLivAttente = [];
  List<DataListeDemandeLivraison> demandeLivEnTraitement = [];
  List<DataListeDemandeLivraison> demandeLivTermine = [];
  List<DataListeDemandeLivraison> demandeLivLivre = [];
  RetourListeDemandeLivraison retListe = RetourListeDemandeLivraison();

  chargerDemandeLivraison() async {
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
            .post(Uri.parse('${lienAPI()}liste-demande-livraison'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retListe = RetourListeDemandeLivraison.fromJson(datas);
            if (retListe.code == 200) {
              setState(() {
                var liste = retListe.data ?? [];
                demandeLivAttente = liste.where((c) => c.etatCommande == COMMANDE_EN_ATTENTE).toList();
                demandeLivEnTraitement = liste.where((c) => c.etatCommande == COMMANDE_EN_TRAITEMENT).toList();
                demandeLivTermine = liste.where((c) => c.etatCommande == COMMANDE_TERMINE).toList();
                pages = [
                  ListeDemandeLivraison(liste: demandeLivAttente),
                  ListeDemandeLivraison(liste: demandeLivEnTraitement),
                  ListeDemandeLivraison(liste: demandeLivTermine),
                ];
              });
            }else{
              EasyLoading.showError(retListe.message ?? '');
            }
        }
      } catch (e) {
        EasyLoading.showError("Une erreur s'est produite veuillez reesayer plus tard");
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
      ListeDemandeLivraison(liste: demandeLivAttente),
      ListeDemandeLivraison(liste: demandeLivEnTraitement),
      ListeDemandeLivraison(liste: demandeLivTermine),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDemandeLivraison();
    });
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des demandes de livraison"),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () => Get.toNamed(DemandeLivraisonScreen.routeName),
        icon: const Icon(Icons.add),
        label: const Text('Nouvelle demande'),
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
                  unselectedLabelStyle: const TextStyle(color: whiteColor),
                  labelStyle:
                  const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                  tabs: const [
                    Tab(icon: Icon(Icons.pause), text: "En Attente"),
                    Tab(icon: Icon(Icons.play_arrow_outlined), text: "En Traitement"),
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
