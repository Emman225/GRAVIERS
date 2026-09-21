import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_liste_demande_livraison.dart';
import 'package:mon_gravier_com/screens/demande_livraison/demande_livraison_screen.dart';

import '../../components/bouton_retour.dart';
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
              // Écran quitté pendant le chargement : la réponse revient sur un écran
              // détruit et le rafraîchissement échoue (voir devis_screen.dart).
              if (mounted) setState(() {
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
              afficherErreur(retListe.message ?? '');
            }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
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
        elevation: 0,
        leading: const BoutonRetour(),
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
          child: DefaultTabController(
            length: pages.length,
            child: Column(
              children: <Widget>[
                ButtonsTabBar(
                  radius: kRadiusPill,
                  // 64 = 40 de pastille + 12 d'air au-dessus et au-dessous.
                  // A 42 sans marge verticale, les onglets touchaient la
                  // section du dessus et le champ de recherche du dessous.
                  height: 64,
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: kSpaceLg),
                  buttonMargin: const EdgeInsets.symmetric(
                      vertical: kSpaceMd, horizontal: 3),
                  backgroundColor: kPrimaryColor,
                  unselectedBackgroundColor: kSurfaceColor,
                  borderWidth: 1.4,
                  borderColor: kPrimaryColor,
                  // L'onglet inactif etait gris sur gris : rien ne disait qu'il
                  // etait cliquable. Contour, libelle et pictogramme prennent
                  // le bleu de la marque — c'est la couleur qui porte
                  // l'information, le remplissage qui dit lequel est ouvert.
                  unselectedBorderColor: kPrimaryColor,
                  labelSpacing: kSpaceSm,
                  labelStyle: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w700),
                  unselectedLabelStyle: const TextStyle(
                      color: kPrimaryColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w600),
                  tabs: const [
                    Tab(icon: Icon(Icons.pause, size: 17), text: "En Attente"),
                    Tab(icon: Icon(Icons.play_arrow_outlined, size: 17), text: "En Traitement"),
                    Tab(icon: Icon(Icons.flag_outlined, size: 17), text: "Terminé"),
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
