import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/Commande.dart';
import 'package:mon_gravier_com/screens/commande/components/commande_liste_screen.dart';

import '../../components/bouton_retour.dart';
import '../../components/onglets.dart';
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

  chargerCommande({bool sansLoader = false}) async {
    if (await verifierConnexion()) {
      // Au glisser, l'indicateur du geste suffit : le voile par-dessus
      // masquerait justement ce qu'on vient de tirer pour voir.
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
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
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
                CommandeListeScreen(commandes: commandeAttente,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
                CommandeListeScreen(commandes: commandeEnTraitement,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
                CommandeListeScreen(commandes: commandeTermine,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
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
            afficherErreur(com.message ?? '');
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
      if (sansLoader == false) {
        fermerChargement();
      }
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    pagesCommande = [
      CommandeListeScreen(commandes: commandeAttente,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
      CommandeListeScreen(commandes: commandeEnTraitement,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
      CommandeListeScreen(commandes: commandeTermine,
                    onRafraichir: () => chargerCommande(sansLoader: true)),
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
        // Cet écran sert À LA FOIS d'onglet et d'écran ouvert depuis
        // « Mon espace ». `afficheRetour` distingue les deux : dans un cas on
        // dépile, dans l'autre on ramène à l'accueil.
        leading: afficheRetour
            ? const BoutonRetour()
            : BoutonRetour(
                onTap: retourAccueil,
                tooltip: "Retour à l'accueil",
              ),
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ContainedTabBarView(
                      tabBarProperties: ongletsSegmentes(),
                      tabs: const [
                        Text('Commande'),
                        Text('Location'),
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
            radius: kRadiusPill,
                  // 64 = 40 de pastille + 12 d'air au-dessus et au-dessous.
                  // A 42 sans marge verticale, les onglets touchaient la
                  // section du dessus et la liste du dessous.
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
              Tab(icon: Icon(Icons.flag_outlined, size: 17), text: "Terminée"),
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
            radius: kRadiusPill,
                  // 64 = 40 de pastille + 12 d'air au-dessus et au-dessous.
                  // A 42 sans marge verticale, les onglets touchaient la
                  // section du dessus et la liste du dessous.
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
              Tab(icon: Icon(Icons.play_arrow_outlined, size: 17), text: "En Cours"),
              Tab(icon: Icon(Icons.flag_outlined, size: 17), text: "Terminée"),
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
