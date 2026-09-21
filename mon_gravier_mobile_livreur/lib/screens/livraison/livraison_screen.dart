import 'dart:convert';

import 'package:buttons_tabbar/buttons_tabbar.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_liste_screen.dart';

import '../../../components/bouton_retour.dart';
import '../../helper/constants.dart';

class LivraisonScreen extends StatefulWidget {
  const LivraisonScreen({super.key});

  @override
  State<LivraisonScreen> createState() => LivraisonScreenState();
}

class LivraisonScreenState extends State<LivraisonScreen>
    with SingleTickerProviderStateMixin {
  /// LES ONGLETS SONT PILOTABLES DE L'EXTERIEUR.
  ///
  /// `DefaultTabController` ne le permettait pas : depuis l'accueil, taper
  /// « EFFECTUEES » amenait bien sur cet ecran, mais toujours sur le premier
  /// onglet. Et comme l'ecran reste vivant d'un passage a l'autre, un
  /// `initialIndex` n'aurait servi qu'une seule fois.
  late final TabController _onglets =
      TabController(length: 3, vsync: this);

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
        final http.Response retourHttp = await http
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
            if (mounted) setState(() {
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
              pages = _pages();
            });
          } else {
            if (mounted) afficherErreur(liv.message ?? '');
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        if (mounted) afficherErreur(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (sansLoader == false) {
        fermerChargement();
      }
    } else {
      if (mounted) afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  /// Les trois onglets, batis sur les listes du moment.
  List<Widget> _pages() => [
        for (final liste in [
          livraisonEnAttente,
          livraisonEnTraitement,
          livraisonEffectue,
        ])
          LivraisonListeScreen(
            livraisons: liste,
            onRetour: () => chargerLivraison(sansLoader: true),
            onRafraichir: () => chargerLivraison(sansLoader: true),
          ),
      ];

  @override
  void initState() {
    pages = _pages();
    super.initState();
    // L'accueil designe l'onglet a ouvrir ; c'est ici, et seulement ici,
    // qu'il se change.
    allerAOngletLivraison = (i) {
      if (mounted && i >= 0 && i < _onglets.length) {
        _onglets.index = i;
        ongletLivraisonDemande = null;
      }
    };

    // L'ECRAN VIENT D'ETRE CONSTRUIT ET UN ONGLET ETAIT DEMANDE.
    //
    // C'est le cas apres une livraison close si l'onglet « Livraison »
    // n'avait pas encore ete ouvert : la demande avait ete posee avant
    // que cet ecran n'existe.
    final demande = ongletLivraisonDemande;
    if (demande != null && demande >= 0 && demande < _onglets.length) {
      _onglets.index = demande;
      ongletLivraisonDemande = null;
    }
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
    if (allerAOngletLivraison != null) allerAOngletLivraison = null;
    _onglets.dispose();
    super.dispose();
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        // Onglet de la barre du bas : rien à dépiler, le retour
        // ramène à l'accueil.
        leading: BoutonRetour(
          onTap: retourAccueil,
          tooltip: "Retour à l'accueil",
        ),
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
          child: Column(
              children: <Widget>[
                ButtonsTabBar(
                  controller: _onglets,
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
                    controller: _onglets,
                    children: pages,
                  ),
                ),
              ],
          ),
        ),
      ),
    );
  }
}
