import 'package:flutter/material.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/screens/cart/cart_screen.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';
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
      backgroundColor: kScaffoldColor,
      appBar: AppBar(
        leading: const BoutonRetour(),
        title: const Text("Fiche produit"),
        actions: [
          // La note se lisait « 4.0 ★ » dans une pastille blanche posée sur
          // l'image, collée au bouton du panier : deux fonctions sans rapport
          // dans un même cadre.
          // MÊME GABARIT QUE LE BOUTON DU PANIER, juste à côté.
          //
          // C'était une gélule étirée par 12 px de marge de chaque côté : deux
          // fois plus large que le bouton voisin, elle déséquilibrait la barre.
          // Elle devient un cercle de 46 px, comme lui.
          Container(
            height: 46,
            width: 46,
            decoration: const BoxDecoration(
              color: kChipSurAppBar,
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  product.meilleurNote.toString(),
                  style: const TextStyle(
                    fontSize: 13,
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    height: 1,
                  ),
                ),
                const SizedBox(width: 1),
                const Icon(Icons.star_rounded, size: 13, color: kAccentClairColor),
              ],
            ),
          ),
          const SizedBox(width: kSpaceMd),
          IconBtnWithCounter(
            svgSrc: "assets/icons/Cart Icon.svg",
            surFondSombre: true,
            press: () => Navigator.pushNamed(context, CartScreen.routeName),
          ),
          const SizedBox(width: kSpaceLg),
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
                    // Clavier français : la virgule est le séparateur décimal par
                    // défaut. double.parse("2,5") levait une exception non gérée
                    // (bouton mort / écran rouge).
                    final qte = double.tryParse(quantiteStr.replaceAll(',', '.'));
                    if (qte == null) {
                      afficherErreur("Veuillez saisir une quantité valide");
                      return;
                    }
                    // Location : une date de fin antérieure à la date de début donne
                    // un nombre de jours nul ou négatif, donc un total nul ou négatif.
                    final nbJours = product.type_affaire == LOCATION
                        ? nombreDeJoursEntre2Dates(debutStr, finStr)
                        : 1;
                    if (product.type_affaire == LOCATION && (nbJours == null || nbJours <= 0)) {
                      afficherErreur(
                          "La date de fin doit être postérieure ou égale à la date de début");
                      return;
                    }
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
                              nbreJours: nbJours,
                              dateDebut: debutStr,
                              dateDeFin: finStr));
                        });
                        afficherSucces("Ajouté au panier avec succès");
                      } else {
                        afficherInfo("Déjà présent dans votre panier");
                      }
                    } else {
                      afficherErreur(
                          "Veuillez saisir une quantité supérieure à 0");
                    }
                  } else {
                    afficherErreur(
                        "Veuillez saisir une quantité valide");
                  }
                } else {
                  afficherErreur(
                      "Veuillez choisir des produits en ${paniers.first.product.type_affaire}");
                }
              },
              child: const Text("Ajouter au panier"),
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
