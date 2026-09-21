import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:liquid_pull_to_refresh/liquid_pull_to_refresh.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:http/http.dart' as http;

import '../../components/etat_vide.dart';
import '../../globale.dart';
import '../../models/ConfigModel.dart';
import 'components/categories.dart';
import 'components/home_header.dart';
import 'components/popular_product.dart';
import 'components/special_offers.dart';
import 'components/top_silder.dart';

class HomeScreen extends StatefulWidget {
  static String routeName = "/home";

  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final GlobalKey<LiquidPullToRefreshState> _refreshIndicatorKey =
      GlobalKey<LiquidPullToRefreshState>();

  /// GLISSER DU HAUT VERS LE BAS.
  ///
  /// Deux défauts ici, que le geste rendait visibles :
  ///
  ///  · le VOILE de chargement se posait par-dessus, masquant justement ce
  ///    qu'on venait de tirer pour voir ;
  ///  · le rechargement n'était pas ATTENDU — un `Completer` était créé, armé
  ///    d'un minuteur de cinq secondes, puis jamais renvoyé ni complété : la
  ///    roue disparaissait aussitôt, avant que les données n'arrivent.
  Future<void> _handleRefresh() => getConfigData(sansLoader: true);

  List<Bannieres> bannieresHaut = [];
  List<Bannieres> bannieresMilieu = [];
  List<Categories> categories = [];
  List<Produits> produits = [];

  Future<void> getConfigData({bool sansLoader = false}) async {
    if (await verifierConnexion()) {
      // Au glisser, l'indicateur du geste suffit : le voile par-dessus
      // masquerait justement ce qu'on vient de tirer pour voir.
      if (sansLoader == false) {
        afficherChargement();
      }
      try {
        String configUrl = '${lienAPI()}get-config';
        if (user.token != null && user.token!.isNotEmpty) {
          configUrl += '?access=${Uri.encodeComponent(user.token!)}';
        }
        retourHttp = await http
            .get(Uri.parse(configUrl))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          user.configs = ConfigModel.fromJson(datas);

          var ban = user.configs?.bannieres ?? [];
          bannieresHaut =
              ban.where((b) => b.typeBanniere == BANNIERE_TOP).toList();
          bannieresMilieu =
              ban.where((b) => b.typeBanniere == BANNIERE_FLASH).toList();

          categories = user.configs?.categories ?? [];
          produits = user.configs?.produits ?? [];

          // Rafraîchir seulement si l'écran est encore affiché (voir
          // chargerPoint()). Les données ci-dessus sont conservées dans tous
          // les cas : rien n'est perdu si l'écran a été quitté.
          if (mounted) setState(() {});
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
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

  chargerPoint() async {
    if (await verifierConnexion()) {
      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };
      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}recuperer-montant-point'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        // Le serveur doit répondre par un objet. S'il renvoie autre chose (une
        // liste, un texte), « datas['code'] » échoue sur une erreur de type
        // que rien ne distingue d'un vrai défaut de l'application. Mieux vaut
        // nommer la situation que la laisser passer pour une panne.
        if (datas is! Map) {
          afficherErreur("Réponse inattendue du serveur pour les points de "
              "fidélité (${datas.runtimeType}).");
          return;
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            montantPoint = double.tryParse(datas['montantPoint']?.toString() ?? '0') ?? 0;
            nombrePoint = double.tryParse(datas['nombrePoint']?.toString() ?? '0') ?? 0;
            // Plancher de paiement : voir `pointsUtilisables`. Une valeur
            // absente laisse 0, c'est-à-dire aucune limite — l'ancien
            // comportement, plutôt qu'un plancher inventé côté application.
            montantMinimumAPayer =
                double.tryParse(datas['montantMinimum']?.toString() ?? '0') ?? 0;
            // Avance disponible et crédits à régler en agence (10/09/2026).
            lireMontantsTableauDeBord(datas);
            // Sans garde, un type inattendu levait une exception avalée par le catch :
            // tva restait à 0 et TOUS les totaux du panier étaient calculés hors taxe,
            // sans que personne ne le voie.
            tva = int.tryParse(datas['tva']?.toString() ?? '0') ?? 0;
            tvaTransport = int.tryParse(datas['tvaTransport']?.toString() ?? '0') ?? 0;
            // AIRSI (10/09/2026) : 0 pour un client au réel.
            tauxAirsi = double.tryParse(datas['tauxAirsi']?.toString() ?? '0') ?? 0;
            devise = datas['devise']?.toString() ?? '';
            // montantTva est déjà positionné par getTotalAmount() à partir du HT ;
            // le recalculer ici sur un montant TVA INCLUSE le surévaluait, et cette
            // valeur partait telle quelle vers l'API (resume-commande, enregistrer-commande).
            getTotalAmount();

            // « Aller à l'accueil » après une commande reconstruit toute la
            // pile de navigation : l'accueil bâti pendant la transition est
            // remplacé alors que cet appel est encore en vol, et la réponse
            // revient sur un écran qui n'existe plus.
            //
            // setState() se termine par « _element! » dans le framework
            // (framework.dart:1219). En debug une assertion explique la
            // situation ; en build release elle est retirée et il ne reste que
            // l'erreur de type. C'est ce « [_TypeError] » qui s'affichait à
            // chaque retour à l'accueil : le serveur n'y était pour rien.
            //
            // Les valeurs globales ci-dessus (tva, points, devise) restent
            // affectées dans tous les cas — les sauter ferait calculer les
            // paniers hors taxe.
            if (mounted) setState(() {});
          } else {
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        if (kDebugMode) {
          print(e.toString());
        }
        afficherErreur(messageErreurTechnique(e));
      }
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    determinePosition();
    var lig = paniers.indexWhere((p) => p.type == 2);
    if (lig >= 0) {
      paniers.clear();
      devisRepris = null;
    }
    super.initState();

    // L'ACCUEIL SE LAISSE RECHARGER DE L'EXTERIEUR.
    //
    // Depuis que les onglets restent vivants, y revenir ne recharge
    // plus : ses chiffres resteraient ceux d'avant l'action faite
    // ailleurs. Silencieux : l'accueil n'est meme pas a l'ecran.
    rafraichirAccueil = () async {
      if (!mounted) return;
      // Le catalogue ET les points de fidélité : une commande passée change
      // les deux, et n'en recharger qu'un laisserait l'autre faux.
      await getConfigData(sansLoader: true);
      if (mounted) await chargerPoint();
    };

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if(categories.isEmpty && produits.isEmpty && bannieresHaut.isEmpty) {
        getConfigData();
      }
      if (user.token != null && user.token != '') {
        chargerPoint();
      }
    });
  }

  @override
  void dispose() {
    // On ne laisse pas un point d'entree pointer sur un ecran detruit.
    rafraichirAccueil = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Le catalogue n'est pas encore arrivé : ni bannière, ni catégorie, ni
    // produit. L'accueil affichait alors un en-tête seul au-dessus d'une page
    // blanche, sans rien dire — impossible de distinguer un chargement en
    // cours d'un serveur qui ne répond pas.
    final bool rienACharger =
        bannieresHaut.isEmpty && categories.isEmpty && produits.isEmpty;

    return Scaffold(
      // UNE SEULE surface défilante. Le bandeau bleu en fait partie : il se
      // replie avec le geste, et seule sa ligne du haut demeure. Il était
      // auparavant posé HORS du défilement, ce qui faisait passer le contenu
      // dessous avec une rupture nette dès le premier geste.
      body: LiquidPullToRefresh(
        showChildOpacityTransition: false,
        onRefresh: _handleRefresh,
        height: MediaQuery.of(context).size.height / 10,
        key: _refreshIndicatorKey,
        color: kPrimaryColor,
        backgroundColor: whiteColor,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics()),
          slivers: [
            const EnTeteAccueil(),
            SliverList(
              delegate: SliverChildListDelegate([
                // TOUTES les catégories, sur une seule rangée qui défile.
                CategoriesArticle(categories: categories),
                TopSlider(items: bannieresHaut),
                SpecialOffers(items: bannieresMilieu),
                const SizedBox(height: kSpaceXl),
                if (rienACharger)
                  // Hauteur bornée : EtatVide porte son propre défilement, et
                  // un défilement dans un défilement recevrait une hauteur
                  // infinie.
                  SizedBox(
                    height: 300,
                    child: EtatVide(
                      icone: Icons.wifi_tethering_off_outlined,
                      titre: "Catalogue indisponible",
                      message: "Nous n'avons pas pu charger les produits. "
                          "Vérifiez votre connexion, puis réessayez.",
                      libelleAction: "Réessayer",
                      action: getConfigData,
                    ),
                  )
                else
                  PopularProducts(produits: produits),
                // Marge basse : la barre d'onglets et le bouton du panier
                // recouvraient la dernière carte de la liste.
                const SizedBox(height: 96),
              ]),
            ),
          ],
        ),
      ),
    );
  }
}
