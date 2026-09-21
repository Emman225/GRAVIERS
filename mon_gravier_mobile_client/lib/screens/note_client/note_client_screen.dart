import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../../constants.dart';
import '../../helper/constants.dart';
import 'components/top_rounded_container.dart';
import 'package:custom_rating_bar/custom_rating_bar.dart';
import 'package:http/http.dart' as http;

class NoteClientScreen extends StatefulWidget {
  static String routeName = "/note_client";

  const NoteClientScreen({super.key});

  @override
  State<NoteClientScreen> createState() => _NoteClientScreenState();
}

class _NoteClientScreenState extends State<NoteClientScreen> {
  // LigneCommande ligneCommande = LigneCommande();
  List<int> ids = [];
  TextEditingController avisController = TextEditingController();
  double note = 4;

  @override
  void initState() {
    avisController = TextEditingController();
    ids = _lireIdentifiants(Get.arguments);
    super.initState();
  }

  /// IDENTIFIANTS DES PRODUITS À NOTER.
  ///
  /// `ids = Get.arguments` était écrit tel quel. L'écran « Détails location »
  /// appelait cet écran en lui passant un OBJET `LigneCommande` — un reste de
  /// l'époque où l'on ne notait qu'un produit à la fois. Affecter un objet à
  /// une `List<int>` lève un `_TypeError` DANS initState : l'écran ne se
  /// construit jamais, et le client tombe sur une page entièrement grise, sans
  /// message ni bouton de retour.
  ///
  /// On accepte désormais les trois formes rencontrées, et l'absence
  /// d'identifiant se dit à l'écran au lieu de le faire disparaître.
  List<int> _lireIdentifiants(dynamic argument) {
    if (argument is List) {
      return argument.whereType<int>().toList();
    }
    if (argument is int) {
      return [argument];
    }
    return const [];
  }

  @override
  void dispose() {
    avisController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Aucun produit identifiable : on le DIT. Un écran vide ne se distingue
    // pas d'une panne.
    if (ids.isEmpty) {
      return Scaffold(
        appBar: AppBar(
          leading: const BoutonRetour(),
          title: const Text("Noter un produit"),
        ),
        body: EtatVide(
          icone: Icons.star_outline,
          titre: "Produit introuvable",
          message: "Nous n'avons pas pu identifier l'article à noter. "
              "Revenez en arrière et réessayez depuis la liste.",
          libelleAction: "Retour",
          action: () => Get.back(),
        ),
      );
    }

    return Scaffold(
      extendBody: true,
      extendBodyBehindAppBar: true,
      backgroundColor: kSurfaceMutedColor,
      appBar: AppBar(
        title: const Text("Noter un produit"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: ListView(
        children: [
          // ProductImages(image: ligneCommande.image ?? '', images: const []),
          TopRoundedContainer(
            color: Colors.white,
            child: Column(
              mainAxisAlignment: mainStart,
              crossAxisAlignment: crossStart,
              children: [
                // Padding(
                //   padding: const EdgeInsets.symmetric(horizontal: 20),
                //   child: Column(
                //     mainAxisAlignment: mainStart,
                //     crossAxisAlignment: crossStart,
                //     children: [
                //       Text(
                //         ligneCommande.nom.toString(),
                //         style: Theme.of(context).textTheme.titleLarge,
                //       ),
                //       Text("#${ligneCommande.reference}",
                //           style: red14MediumTextStyle),
                //       addVerticalSpace(20),
                //       Text(formaterMontant(ligneCommande.prixReduction!.toDouble()),
                //           style: Theme.of(context).textTheme.titleLarge),
                //     ],
                //   ),
                // ),
                // Padding(
                //   padding: const EdgeInsets.only(
                //     left: 20,
                //     right: 64,
                //   ),
                //   child: Text(
                //     ligneCommande.description.toString(),
                //     maxLines: 3,
                //   ),
                // ),
                addVerticalSpace(30),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: RatingBar(
                    filledIcon: Icons.star,
                    emptyIcon: Icons.star_border,
                    onRatingChanged: (value) => setState(() {
                      note = value;
                    }),
                    initialRating: note,
                    maxRating: 5,
                  ),
                ),
                addVerticalSpace(40),
                Padding(
                  padding: const EdgeInsets.all(8.0),
                  child: TextFormField(
                    keyboardType: TextInputType.text,
                    maxLines: 4,
                    maxLength: 100,
                    controller: avisController,
                    textInputAction: TextInputAction.done,
                    decoration: const InputDecoration(
                      labelText: "Votre avis sur le/les produit(s) *",
                      hintText: "Saisissez votre avis ici...",
                      // If  you are using latest version of flutter then lable text and hint text shown like this
                      // if you r using flutter less then 1.20.* then maybe this is not working properly
                      floatingLabelBehavior: FloatingLabelBehavior.always,
                    ),
                  ),
                ),
                addVerticalSpace(30),
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
              onPressed: () async {

                if (avisController.text.trim() != '') {
                  if (await verifierConnexion()) {
                    try {
                      afficherChargement();

                      var param = {
                        'access': user.token.toString(),
                        'type': user.type.toString(),
                        'note': note,
                        'avis': avisController.text.trim(),
                        'produit_id': ids,
                      };

                      if (kDebugMode) {
                        print(param);
                      }

                      retourHttp = await http
                          .post(Uri.parse('${lienAPI()}enregistrer-note'),
                          headers: {"Content-Type": "application/json"},
                          body: jsonEncode(param))
                          .timeout(const Duration(minutes: 2));

                      var datas = jsonDecode(retourHttp.body);

                      if (kDebugMode) {
                        print(datas);
                      }

                      if (retourHttp.statusCode == 200) {
                        if (datas['code'] == 200) {
                          afficherSucces(datas['message']);
                          Get.back();
                        } else {
                          afficherErreur(datas['message']);
                        }
                      } else {
                        // Sans cette branche, une réponse serveur en erreur ne produisait
                        // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
                        afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
                      }
                    } catch (e) {
                      user.code = 500;
                      user.message =
                      messageErreurTechnique(e);
                      if (kDebugMode) {
                        print(e.toString());
                      }
                    }

                    fermerChargement();
                  } else {
                    afficherInfo(
                        "Veuillez vérifier votre connexion internet");
                  }
                }else{
                  afficherInfo(
                      "Veuillez saisir votre avis sur ce produit");
                }
              },
              child: const Text("Enregistrer ma note"),
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
