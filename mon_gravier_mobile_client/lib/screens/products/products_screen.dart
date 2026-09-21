import 'dart:convert';

import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mon_gravier_com/components/product_card.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/filtre_produits.dart';

import '../../constants.dart';
import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../../components/onglets.dart';
import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../cart/cart_screen.dart';
import '../details/details_screen.dart';
import '../home/components/icon_btn_with_counter.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key, this.titre, this.selection});

  /// Titre de l'écran quand il est ouvert depuis une bannière (09/09/2026).
  final String? titre;

  /// Sélection appliquée au catalogue : « top » (les mieux notés) ou « flash »
  /// (les produits à prix réduit). Sans sélection : tout le catalogue.
  final String? selection;

  /// Ouvert depuis une bannière : le titre suit, et le retour dépile.
  bool get depuisBanniere => selection != null;

  static String routeName = "/products";

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  List<Produits> produits = [];
  List<Produits> produitSearch = [];

  // CE QUI EST COCHÉ, pour que le panneau rouvre dessus. Sans mémoire, le
  // client ne voyait pas quel filtre était actif et ne pouvait pas le retirer.
  Map<String, List<String>> selectionFiltres = {};

  // LE CATALOGUE COMPLET, figé au premier chargement sans filtre.
  //
  // Les options proposées étaient construites depuis la liste AFFICHÉE : une
  // fois un filtre posé, la liste rétrécit, et le choix offert avec elle. On
  // pouvait restreindre encore, jamais revenir en arrière.
  List<Produits> catalogueComplet = [];

  List<String> cat = [];
  List<String> prod = [];
  List<String> mont = [];

  chargerProduit({bool sansLoader = false}) async {
    if (await verifierConnexion()) {
      // Au glisser, l'indicateur du geste suffit : le voile par-dessus
      // masquerait justement ce qu'on vient de tirer pour voir.
      if (sansLoader == false) {
        afficherChargement();
      }

      var param = {
        "categories": cat,
        "produits": prod,
        "montants": mont,
        if (user.token != null && user.token!.isNotEmpty) "access": user.token,
      };

      if (kDebugMode) {
        print("USER TOKEN: '${user.token}'");
        print("PARAMETRES ENVOYES: $param");
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-produit'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          setState(() {
            produits = _appliquerSelection(Produits.fromListJson(datas));
            produitSearch = produits;
            // Sans filtre, ce que le serveur rend EST le catalogue : c'est le
            // seul moment où l'on peut en garder la liste complète.
            if (cat.isEmpty && prod.isEmpty && mont.isEmpty) {
              catalogueComplet = produits;
            }
          });
          if (kDebugMode) {
            print("-------------${produits.length}");
          }
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

  /// LA SÉLECTION D'UNE BANNIÈRE (09/09/2026).
  ///
  /// « top » : les produits notés 4 et plus, du mieux noté au moins bien ; à
  /// défaut, tout le catalogue dans cet ordre. « flash » : les produits dont
  /// le prix réduit est inférieur au prix courant ; à défaut, tout le
  /// catalogue, et le client en est informé. Une sélection vide qui laisserait
  /// l'écran blanc n'apprendrait rien au client.
  List<Produits> _appliquerSelection(List<Produits> liste) {
    if (widget.selection == 'top') {
      final tries = List<Produits>.from(liste)
        ..sort((a, b) => (b.meilleurNote ?? 0).compareTo(a.meilleurNote ?? 0));
      final meilleurs = tries.where((p) => (p.meilleurNote ?? 0) >= 4).toList();
      return meilleurs.isNotEmpty ? meilleurs : tries;
    }
    if (widget.selection == 'flash') {
      final promos = liste
          .where((p) => (p.prixReduction ?? 0) > 0 && (p.prixReduction ?? 0) < (p.prixMoyen ?? 0))
          .toList();
      if (promos.isEmpty) {
        afficherInfo("Aucune offre flash en ce moment : voici tout le catalogue.");
        return liste;
      }
      return promos;
    }
    return liste;
  }

  @override
  void initState() {
    var lig = paniers.indexWhere((p) => p.type == 2);
    if (lig >= 0) {
      paniers.clear();
      devisRepris = null;
    }
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerProduit();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.titre ?? "Liste des produits"),
        // Onglet de la barre du bas : rien à dépiler, le retour ramène à
        // l'accueil. Ouvert depuis une bannière : le retour dépile.
        leading: widget.depuisBanniere
            ? const BoutonRetour()
            : BoutonRetour(
                onTap: retourAccueil,
                tooltip: "Retour à l'accueil",
              ),
        automaticallyImplyLeading: false,
        actions: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 5),
            child: IconBtnWithCounter(
              svgSrc: "assets/icons/Cart Icon.svg",
            surFondSombre: true,
              press: () => Navigator.pushNamed(context, CartScreen.routeName),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          showModalBottomSheet(
            context: context,
            builder: (_) {
              return FiltreProduits(
                selection: selectionFiltres,
                groupes: [
                  GroupeFiltre(
                    cle: 'Categorie',
                    titre: 'Catégorie',
                    options: getCategories(),
                  ),
                  GroupeFiltre(
                    cle: 'Produit',
                    titre: 'Produit',
                    options: getProduits(),
                  ),
                  GroupeFiltre(
                    cle: 'Montant',
                    titre: 'Montant',
                    options: getMontant(),
                  ),
                ],
                onValider: (choix) {
                  // Le panneau rend la sélection complète : on la garde pour
                  // qu'il rouvre dessus, puis on recharge.
                  selectionFiltres = choix;
                  cat = choix['Categorie'] ?? [];
                  prod = choix['Produit'] ?? [];
                  mont = choix['Montant'] ?? [];
                  chargerProduit();
                },
              );
            },
          );
        },
        tooltip: 'Filtrer les produits',
        child: const Icon(Icons.filter_list),
      ),
      body: SafeArea(
              child: Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ContainedTabBarView(
                      tabBarProperties: ongletsSegmentes(),
                    tabs: const [
                      Text('Vente'),
                      Text('Location'),
                    ],
                    views: [
                      _listeWidgetVente(),
                      _listeWidgetLocation(),
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

  /// GLISSER DU HAUT VERS LE BAS POUR ACTUALISER.
  ///
  /// Depuis que les onglets restent vivants, c'est la SEULE facon de
  /// remettre le catalogue a jour : changer d'onglet ne le recharge plus.
  Widget _avecGlisser(Widget contenu) {
    return RefreshIndicator(
      color: kPrimaryColor,
      onRefresh: () => chargerProduit(sansLoader: true),
      child: contenu,
    );
  }

  _listeWidgetLocation(){
    List<Produits> prods = produits.where((p) => p.type_affaire == LOCATION).toList();
    if (prods.isEmpty) {
      // Une liste vide reste defilable : c'est celle qu'on veut recharger.
      return _avecGlisser(ListView(
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        children: const [SizedBox(height: 80), EtatVide(
        icone: Icons.inventory_2_outlined,
        titre: "Aucun article ici",
        message:
            "Aucun produit ne correspond pour le moment. Modifiez vos filtres "
            "ou revenez un peu plus tard.",
        )],
      ));
    }
    return _avecGlisser(GridView.builder(
      physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.only(bottom: 96),
      itemCount: prods.length,
      gridDelegate:
      const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 200,
        childAspectRatio: 0.7,
        mainAxisSpacing: 20,
        crossAxisSpacing: 16,
      ),
      itemBuilder: (context, index) => ProductCard(
        product: prods[index],
        onPress: () => Navigator.pushNamed(
          context,
          DetailsScreen.routeName,
          arguments: ProductDetailsArguments(
              product: prods[index]),
        ), onLongPress: () {  },
      ),
    ));
  }

  _listeWidgetVente(){
    List<Produits> prods = produits.where((p) => p.type_affaire == VENTE).toList();
    if (prods.isEmpty) {
      // Une liste vide reste defilable : c'est celle qu'on veut recharger.
      return _avecGlisser(ListView(
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        children: const [SizedBox(height: 80), EtatVide(
        icone: Icons.inventory_2_outlined,
        titre: "Aucun article ici",
        message:
            "Aucun produit ne correspond pour le moment. Modifiez vos filtres "
            "ou revenez un peu plus tard.",
        )],
      ));
    }
    return _avecGlisser(GridView.builder(
      physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics()),
      padding: const EdgeInsets.only(bottom: 96),
      itemCount: prods.length,
      gridDelegate:
      const SliverGridDelegateWithMaxCrossAxisExtent(
        maxCrossAxisExtent: 200,
        childAspectRatio: 0.7,
        mainAxisSpacing: 20,
        crossAxisSpacing: 16,
      ),
      itemBuilder: (context, index) => ProductCard(
        product: prods[index],
        onPress: () => Navigator.pushNamed(
          context,
          DetailsScreen.routeName,
          arguments: ProductDetailsArguments(
              product: prods[index]),
        ), onLongPress: () {  },
      ),
    ));
  }

  List<OptionFiltre> getCategories() {
    List<Categories> cats = user.configs?.categories ?? [];
    return cats
        .map((c) => OptionFiltre(
            libelle: c.nom.toString(), cle: c.id.toString()))
        .toList();
  }

  List<OptionFiltre> getProduits() {
    return (catalogueComplet.isEmpty ? produits : catalogueComplet)
        .map((c) => OptionFiltre(
            libelle: c.nom.toString(), cle: c.id.toString()))
        .toList();
  }

  List<OptionFiltre> getMontant() {
    List<Produits> newProds = [];
    for (var p in (catalogueComplet.isEmpty ? produits : catalogueComplet)) {
      int index = newProds.indexWhere((elt) => elt.prixMoyen == p.prixMoyen);
      if (index == -1) {
        newProds.add(p);
      }
    }
    return newProds
        .map((c) => OptionFiltre(
              libelle: "${formaterMontant(c.prixMoyen!.toDouble())}/T",
              cle: c.prixMoyen.toString(),
            ))
        .toList();
  }
}
