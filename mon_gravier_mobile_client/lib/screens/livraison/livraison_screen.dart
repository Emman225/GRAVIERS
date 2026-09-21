import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:hawk_fab_menu/hawk_fab_menu.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';
import 'package:mon_gravier_com/screens/demande_livraison/demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/liste_demande_livraison/liste_demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/livraison/components/livraison_liste_screen.dart';

import '../../components/bouton_retour.dart';
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

  chargerLivraison({bool sansLoader = false}) async {
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
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
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
                LivraisonListeScreen(livraisons: livraisonAttente,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
                LivraisonListeScreen(livraisons: livraisonEnTraitement,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
                LivraisonListeScreen(livraisons: livraisonEffectue,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
              ];
            });
          } else {
            afficherErreur(liv.message ?? '');
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
    pages = [
      LivraisonListeScreen(livraisons: livraisonAttente,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
      LivraisonListeScreen(livraisons: livraisonEnTraitement,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
      LivraisonListeScreen(livraisons: livraisonEffectue,
                    onRafraichir: () => chargerLivraison(sansLoader: true)),
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
        // Onglet de la barre du bas : rien à dépiler, le retour ramène à
        // l'accueil.
        leading: BoutonRetour(
          onTap: retourAccueil,
          tooltip: "Retour à l'accueil",
        ),
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
          // Le bouton du menu était TURQUOISE (0xff03dac6, l'accent par défaut
          // du thème sombre de Material), l'une de ses entrées ROUGE avec un
          // libellé BLEU, l'autre sur fond bleu : quatre couleurs étrangères à
          // l'application, sur son écran de livraison.
          : HawkFabMenu(
              icon: AnimatedIcons.menu_arrow,
              fabColor: kPrimaryColor,
              iconColor: whiteColor,
              hawkFabMenuController: hawkFabMenuController,
              items: [
                HawkFabMenuItem(
                  label: 'Mes demandes de livraison',
                  ontap: () =>
                      Get.toNamed(ListeDemandeLivraisonScreen.routeName),
                  icon: const Icon(Icons.list_alt_rounded, color: Colors.white),
                  color: kPrimaryColor,
                  labelColor: kTextColor,
                  labelBackgroundColor: kSurfaceColor,
                ),
                HawkFabMenuItem(
                  label: 'Nouvelle demande de livraison',
                  ontap: () => Get.toNamed(DemandeLivraisonScreen.routeName),
                  icon: const Icon(Icons.add, color: Colors.white),
                  color: kAccentColor,
                  labelColor: kTextColor,
                  labelBackgroundColor: kSurfaceColor,
                ),
              ],
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
                            Tab(icon: Icon(Icons.flag_outlined, size: 17), text: "Effectuée"),
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
