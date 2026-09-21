import 'dart:convert';

import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/components/product_card.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/filtre_produits.dart';

import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../../components/onglets.dart';
import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../cart/cart_screen.dart';
import '../details/details_screen.dart';
import '../home/components/icon_btn_with_counter.dart';

class ProductsCategorieScreen extends StatefulWidget {
  const ProductsCategorieScreen({super.key});

  static String routeName = "/products_categorie";

  @override
  State<ProductsCategorieScreen> createState() =>
      _ProductsCategorieScreenState();
}

class _ProductsCategorieScreenState extends State<ProductsCategorieScreen> {
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

  List<String> prod = [];
  List<String> mont = [];

  int idCat = 0;
  String libCat = '';

  chargerProduit() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "categories": [idCat],
        "produits": prod,
        "montants": mont,
        if (user.token != null && user.token!.isNotEmpty) "access": user.token,
      };

      if (kDebugMode) {
        print("USER TOKEN CATEGORIE: '${user.token}'");
        print("PARAMETRES CATEGORIE ENVOYES: $param");
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-produit-cat'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          setState(() {
            produits = Produits.fromListJson(datas);
            produitSearch = produits;
            // Sans filtre, ce que le serveur rend EST le catalogue : c'est le
            // seul moment où l'on peut en garder la liste complète.
            if (prod.isEmpty && mont.isEmpty) {
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
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    super.initState();
    var datas = Get.arguments;
    idCat = datas[0];
    libCat = datas[1];
    var lig = paniers.indexWhere((p) => p.type == 2);
    if (lig >= 0) {
      paniers.clear();
      devisRepris = null;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerProduit();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const BoutonRetour(),
        title: Text("Catégorie: $libCat"),
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

  _listeWidgetLocation(){
    List<Produits> prods = produits.where((p) => p.type_affaire == LOCATION).toList();
    if (prods.isEmpty) {
      return EtatVide(
        icone: Icons.inventory_2_outlined,
        titre: "Aucun article ici",
        message:
            "Aucun produit ne correspond pour le moment. Modifiez vos filtres "
            "ou revenez un peu plus tard.",
      );
    }
    return GridView.builder(
      physics: const BouncingScrollPhysics(),
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
    );
  }

  _listeWidgetVente(){
    List<Produits> prods = produits.where((p) => p.type_affaire == VENTE).toList();
    if (prods.isEmpty) {
      return EtatVide(
        icone: Icons.inventory_2_outlined,
        titre: "Aucun article ici",
        message:
            "Aucun produit ne correspond pour le moment. Modifiez vos filtres "
            "ou revenez un peu plus tard.",
      );
    }
    return GridView.builder(
      physics: const BouncingScrollPhysics(),
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
    );
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
