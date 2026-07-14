import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_souhait.dart';

import '../../components/product_card.dart';
import '../../helper/constants.dart';
import '../../models/ConfigModel.dart';
import '../details/details_screen.dart';

class SouhaitScreen extends StatefulWidget {
  static String routeName = "/souhaitClient";

  const SouhaitScreen({super.key});

  @override
  State<SouhaitScreen> createState() => SouhaitScreenState();
}

class SouhaitScreenState extends State<SouhaitScreen> {
  List<ListeSouhait> souhaits = [];
  RetourSouhait retSouhait = RetourSouhait();
  int idProduit = 0;

  gestionSouhait(niveau) async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "niveau": niveau,
      };

      if (niveau == 3) {
        idProduit = 0;
      }

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(
                Uri.parse(
                    '${lienAPI()}ajouter-retirer-liste-souhait/$idProduit'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          setState(() {
            retSouhait = RetourSouhait.fromJson(datas);
            souhaits = retSouhait.data ?? [];
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      gestionSouhait(3);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Ma liste de souhait"),
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
                  child: GridView.builder(
                    physics: const BouncingScrollPhysics(),
                    itemCount: souhaits.length,
                    gridDelegate:
                        const SliverGridDelegateWithMaxCrossAxisExtent(
                      maxCrossAxisExtent: 200,
                      childAspectRatio: 0.7,
                      mainAxisSpacing: 20,
                      crossAxisSpacing: 16,
                    ),
                    itemBuilder: (context, index) {
                      Produits prod = Produits(
                        unite: souhaits[index].unite,
                        nom: souhaits[index].nom,
                        id: souhaits[index].id,
                        unite_id: souhaits[index].uniteProduitId,
                        description: souhaits[index].description,
                        reference: souhaits[index].reference,
                        prixReduction: souhaits[index].prixReduction,
                        prixMoyen: souhaits[index].prixMoyen,
                        prixPersonnalise: souhaits[index].prixPersonnalise,
                        image: souhaits[index].image,
                        meilleurNote: souhaits[index].meilleurNote,
                        abreviation: souhaits[index].abreviation,
                        images: souhaits[index].images,
                        type_affaire: souhaits[index].type_affaire,
                      );

                      return ProductCard(
                        product: prod,
                        onPress: () => Navigator.pushNamed(
                          context,
                          DetailsScreen.routeName,
                          arguments: ProductDetailsArguments(product: prod),
                        ),
                        onLongPress: () async {
                          if (await confirmationAction(context, "Attention!",
                              "Voulez-vous supprimer ce produit de votre liste de souhait ?")) {
                            idProduit = prod.id ?? 0;
                            gestionSouhait(2);
                          }
                        },
                      );
                    },
                  ),
                ),
              ),
            ),
    );
  }
}
