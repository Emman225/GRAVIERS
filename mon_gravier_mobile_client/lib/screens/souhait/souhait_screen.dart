import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/retour_souhait.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../components/etat_vide.dart';
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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      gestionSouhait(3);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Ma liste de souhait"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
              child: Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: souhaits.isEmpty
                      ? const EtatVide(
                          icone: Icons.favorite_border,
                          titre: "Votre liste de souhaits est vide",
                          message:
                              "Depuis une fiche produit, touchez le coeur pour "
                              "y ajouter un article et le retrouver ici.",
                        )
                      : GridView.builder(
                    physics: const BouncingScrollPhysics(),
                    padding: const EdgeInsets.only(bottom: kSpaceXl),
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
                          if (await confirmationAction(context, "Retirer ce produit",
                              "Voulez-vous retirer ce produit de votre liste de souhaits ?")) {
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
