import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:liquid_pull_to_refresh/liquid_pull_to_refresh.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:http/http.dart' as http;

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

  Future<void> _handleRefresh() async {
    final Completer<void> completer = Completer<void>();
    Timer(const Duration(seconds: 5), () {
      completer.complete();
    });
    getConfigData();
  }

  List<Bannieres> bannieresHaut = [];
  List<Bannieres> bannieresMilieu = [];
  List<Categories> categories = [];
  List<Produits> produits = [];

  getConfigData() async {
    if (await verifierConnexion()) {
      afficherChargement();
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
          setState(() {
            user.configs = ConfigModel.fromJson(datas);

            var ban = user.configs?.bannieres ?? [];
            bannieresHaut =
                ban.where((b) => b.typeBanniere == BANNIERE_TOP).toList();
            bannieresMilieu =
                ban.where((b) => b.typeBanniere == BANNIERE_FLASH).toList();

            categories = user.configs?.categories ?? [];
            produits = user.configs?.produits ?? [];
          });
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
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            montantPoint = double.tryParse(datas['montantPoint']?.toString() ?? '0') ?? 0;
            nombrePoint = double.tryParse(datas['nombrePoint']?.toString() ?? '0') ?? 0;
            tva = datas['tva'];
            montantTva = getTotalAmount() * tva / 100;
            devise = datas['devise'];
            setState(() {});
          } else {
            EasyLoading.showError(datas['message']);
          }
        }
      } catch (e) {
        if (kDebugMode) {
          print(e.toString());
        }
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
      }
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    determinePosition();
    var lig = paniers.indexWhere((p) => p.type == 2);
    if (lig >= 0) {
      paniers.clear();
    }
    super.initState();
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
  Widget build(BuildContext context) {
    return Scaffold(
      body: LiquidPullToRefresh(
        showChildOpacityTransition: false,
        onRefresh: _handleRefresh,
        height: MediaQuery.of(context).size.height / 10,
        key: _refreshIndicatorKey,
        color: kPrimaryColor,
        backgroundColor: whiteColor,
        child: SafeArea(
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
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Column(
                crossAxisAlignment: crossCenter,
                mainAxisAlignment: mainCenter,
                children: [
                  const HomeHeader(),
                  TopSlider(items: bannieresHaut),
                  CategoriesArticle(
                      categories: categories.sublist(0,
                              categories.length < 5 ? categories.length : 5) ??
                          []),
                  if (categories.length >= 6) ...[
                    CategoriesArticle(
                        categories: categories.sublist(
                            5, categories.length < 10 ? categories.length : 10)),
                  ],
                  SpecialOffers(items: bannieresMilieu),
                  const SizedBox(height: 20),
                  PopularProducts(produits: produits),
                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
