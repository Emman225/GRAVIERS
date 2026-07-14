import 'dart:convert';

import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:mon_gravier_com/components/product_card.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:amazon_like_filter/amazon_like_filter.dart';

import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../cart/cart_screen.dart';
import '../details/details_screen.dart';
import '../home/components/icon_btn_with_counter.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  static String routeName = "/products";

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  List<Produits> produits = [];
  List<Produits> produitSearch = [];
  List<AppliedFilterModel> applied = [];

  List<String> cat = [];
  List<String> prod = [];
  List<String> mont = [];

  chargerProduit() async {
    if (await verifierConnexion()) {
      afficherChargement();

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
            produits = Produits.fromListJson(datas);
            produitSearch = produits;
          });
          if (kDebugMode) {
            print("-------------${produits.length}");
          }
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
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
    var lig = paniers.indexWhere((p) => p.type == 2);
    if (lig >= 0) {
      paniers.clear();
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
        title: const Text("Liste des produits"),
        automaticallyImplyLeading: false,
        actions: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 5),
            child: IconBtnWithCounter(
              svgSrc: "assets/icons/Cart Icon.svg",
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
              return FilterWidget(
                  filterProps: FilterProps(
                themeProps: ThemeProps(
                  searchBarViewProps: SearchBarViewProps(
                    searchHint: "Recherchez...",
                  ),
                ),
                title: "Recherchez un produit",
                onFilterChange: (value) {
                  cat = [];
                  prod = [];
                  mont = [];
                  applied = value;
                  for (var f in applied) {
                    if (f.filterKey == 'Categorie') {
                      for (var ap in f.applied) {
                        cat.add(ap.filterKey);
                      }
                    }
                    if (f.filterKey == 'Produit') {
                      for (var ap in f.applied) {
                        prod.add(ap.filterKey);
                      }
                    }
                    if (f.filterKey == 'Montant') {
                      for (var ap in f.applied) {
                        mont.add(ap.filterKey);
                      }
                    }
                    if (kDebugMode) {
                      print(f.filterKey);
                    }
                  }

                  chargerProduit();

                  // setState(() {
                  //   applied = value;
                  // });
                  if (kDebugMode) {
                    print('Applied filer - ${value.map((e) => e.toMap())}');
                  }
                },
                filters: [
                  FilterListModel(
                    filterOptions: getCategories(),
                    previousApplied: const [],
                    title: 'Categorie',
                    filterKey: 'Categorie',
                  ),
                  FilterListModel(
                    filterOptions: getProduits(),
                    previousApplied: const [],
                    title: 'Produit',
                    filterKey: 'Produit',
                  ),
                  FilterListModel(
                    filterOptions: getMontant(),
                    previousApplied: [],
                    title: 'Montant',
                    filterKey: 'Montant',
                  ),
                ],
              ));
            },
          );
        },
        tooltip: 'Increment',
        child: const Icon(Icons.filter_list),
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
                      Text('Vente', style: white16BoldTextStyle),
                      Text('Location', style: white16BoldTextStyle),
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
    return GridView.builder(
      physics: const BouncingScrollPhysics(),
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
    return GridView.builder(
      physics: const BouncingScrollPhysics(),
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

  List<FilterItemModel> getCategories() {
    List<Categories> cats = user.configs?.categories ?? [];
    return cats
        .map((c) => FilterItemModel(
            filterTitle: c.nom.toString(), filterKey: c.id.toString()))
        .toList();
  }

  List<FilterItemModel> getProduits() {
    return produits
        .map((c) => FilterItemModel(
            filterTitle: c.nom.toString(), filterKey: c.id.toString()))
        .toList();
  }

  List<FilterItemModel> getMontant() {
    List<Produits> newProds = [];
    for (var p in produits) {
      int index = newProds.indexWhere((elt) => elt.prixMoyen == p.prixMoyen);
      if (index == -1) {
        newProds.add(p);
      }
    }
    return newProds
        .map((c) => FilterItemModel(
              filterTitle: "${formaterMontant(c.prixMoyen!.toDouble())}/T",
              filterKey: c.prixMoyen.toString(),
            ))
        .toList();
  }
}
