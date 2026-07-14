import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/screens/cart/cart_screen.dart';

import '../home/components/icon_btn_with_counter.dart';
import 'components/product_description.dart';
import 'components/product_images.dart';
import 'components/top_rounded_container.dart';

class DetailsScreen extends StatefulWidget {
  static String routeName = "/details";

  const DetailsScreen({super.key});

  @override
  State<DetailsScreen> createState() => _DetailsScreenState();
}

class _DetailsScreenState extends State<DetailsScreen> {
  TextEditingController qteController = TextEditingController();
  TextEditingController mtnController = TextEditingController();
  TextEditingController debutController = TextEditingController();
  TextEditingController finController = TextEditingController();

  @override
  void initState() {
    qteController = TextEditingController(text: "1");
    mtnController = TextEditingController();
    debutController = TextEditingController();
    finController = TextEditingController();
    super.initState();
  }

  @override
  void dispose() {
    qteController.dispose();
    mtnController.dispose();
    debutController.dispose();
    finController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ProductDetailsArguments agrs =
        ModalRoute.of(context)!.settings.arguments as ProductDetailsArguments;
    final product = agrs.product;
    return Scaffold(
      extendBody: true,
      extendBodyBehindAppBar: true,
      backgroundColor: const Color(0xFFF5F6F9),
      appBar: AppBar(
        title: const Text("Détails du produit"),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
        actions: [
          Row(
            children: [
              Container(
                margin: const EdgeInsets.only(right: 20),
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    Text(
                      product.meilleurNote.toString(),
                      style: const TextStyle(
                        fontSize: 14,
                        color: Colors.black,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(width: 4),
                    SvgPicture.asset("assets/icons/Star Icon.svg"),
                    const SizedBox(width: 16),
                    IconBtnWithCounter(
                      svgSrc: "assets/icons/Cart Icon.svg",
                      press: () =>
                          Navigator.pushNamed(context, CartScreen.routeName),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: ListView(
        children: [
          ProductImages(
              image: product.image ?? '', images: product.images ?? []),
          TopRoundedContainer(
            color: Colors.white,
            child: Column(
              children: [
                ProductDescription(
                  qteController: qteController,
                  mtnController: mtnController,
                  debutController: debutController,
                  finController: finController,
                  product: product,
                  pressOnSeeMore: () {},
                ),
                // TopRoundedContainer(
                //   color: const Color(0xFFF6F7F9),
                //   child: Column(
                //     children: [
                //       ColorDots(product: product),
                //     ],
                //   ),
                // ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: TopRoundedContainer(
        color: Colors.white,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
            child: ElevatedButton(
              onPressed: () {
                final quantiteStr = qteController.text.trim();
                final debutStr = debutController.text.trim();
                final finStr = finController.text.trim();

                bool bPass = true;
                if (paniers.isNotEmpty) {
                  if (paniers.first.product.type_affaire == VENTE &&
                      product.type_affaire != VENTE) {
                    bPass = false;
                  } else if (paniers.first.product.type_affaire == LOCATION &&
                      product.type_affaire != LOCATION) {
                    bPass = false;
                  }
                }
                if (bPass) {
                  if (quantiteStr.isNotEmpty) {
                    final qte = double.parse(quantiteStr);
                    if (qte > 0) {
                      int index =
                          paniers.indexWhere((p) => p.product.id == product.id);
                      if (index == -1) {
                        //Produit inexistant
                        setState(() {
                          paniers.add(Cart(
                              product: product,
                              numOfItem: qte,
                              type: 1,
                              nbreJours: nombreDeJoursEntre2Dates(debutStr, finStr),
                              dateDebut: debutStr,
                              dateDeFin: finStr));
                        });
                        EasyLoading.showSuccess("Ajouté au panier avec succès");
                      } else {
                        EasyLoading.showInfo("Déjà présent dans votre panier");
                      }
                    } else {
                      EasyLoading.showError(
                          "Veuillez saisir une quantité supérieur à 0");
                    }
                  } else {
                    EasyLoading.showError(
                        "Veuillez saisir une quantité valide");
                  }
                } else {
                  EasyLoading.showError(
                      "Veuillez choisir des produits en ${paniers.first.product.type_affaire}");
                }
              },
              child: const Text("Ajouter au pannier"),
            ),
          ),
        ),
      ),
    );
  }
}

class ProductDetailsArguments {
  final Produits product;

  ProductDetailsArguments({required this.product});
}
