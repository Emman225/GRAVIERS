import 'dart:async';
import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_liste_screen.dart';

import '../../helper/constants.dart';

class LivraisonScreen extends StatefulWidget {
  const LivraisonScreen({super.key});

  @override
  State<LivraisonScreen> createState() => LivraisonScreenState();
}

class LivraisonScreenState extends State<LivraisonScreen> {
  List<UneLivraison> livraisonEnAttente = [];
  List<UneLivraison> livraisonEnTraitement = [];
  List<UneLivraison> livraisonEffectue = [];
  RetourLivraison liv = RetourLivraison();
  // Timer? timer;

  chargerLivraison({bool sansLoader = false}) async {
    if (await verifierConnexion()) {

      if (sansLoader == false) {
        afficherChargement();
      }

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
              livraisonEnAttente = livraisons
                  .where((c) => c.etatLivraison == LIVRAISON_EN_ATTENTE)
                  .toList();
              livraisonEnTraitement = livraisons
                  .where((c) => (c.etatLivraison == LIVRAISON_EN_TRAITEMENT || c.etatLivraison == LIVRAISON_EN_COURS))
                  .toList();
              livraisonEffectue = livraisons
                  .where((c) => c.etatLivraison == LIVRAISON_LIVREE)
                  .toList();
              pages = [
                LivraisonListeScreen(livraisons: livraisonEnAttente),
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
      if (sansLoader == false) {
        fermerChargement();
      }
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    pages = [
      LivraisonListeScreen(livraisons: livraisonEnAttente),
      LivraisonListeScreen(livraisons: livraisonEnTraitement),
      LivraisonListeScreen(livraisons: livraisonEffectue),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if(user.token != null && user.token != ""){
        chargerLivraison();
      }
    });
    // timer = Timer.periodic(const Duration(seconds: 15), (Timer t) {
    //   chargerLivraison(sansLoader: true);
    // });
  }

  @override
  void dispose(){
    // timer?.cancel();
    super.dispose();
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des livraisons et traitements"),
        centerTitle: true,
        automaticallyImplyLeading: false,
        actions: [
          IconButton(
              onPressed: () => chargerLivraison(),
              icon: const Icon(
                Icons.refresh_outlined,
                color: kPrimaryColor,
                size: 35,
              )),
          addHorizontalSpace(5),
        ],
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
    );
  }
}
