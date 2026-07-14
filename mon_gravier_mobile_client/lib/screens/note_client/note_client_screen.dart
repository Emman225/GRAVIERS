import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
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
    ids = Get.arguments;
    super.initState();
  }

  @override
  void dispose() {
    avisController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      extendBodyBehindAppBar: true,
      backgroundColor: const Color(0xFFF5F6F9),
      appBar: AppBar(
        title: const Text("Noter un produit"),
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
                      labelText: "Votre avis sur le/les produit(s)",
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
                          EasyLoading.showSuccess(datas['message']);
                          Get.back();
                        } else {
                          EasyLoading.showError(datas['message']);
                        }
                      }
                    } catch (e) {
                      user.code = 500;
                      user.message =
                      "Une erreur s'est produite veuillez reesayer plus tard";
                      if (kDebugMode) {
                        print(e.toString());
                      }
                    }

                    fermerChargement();
                  } else {
                    EasyLoading.showInfo(
                        "Veuillez vérifier votre connexion internet");
                  }
                }else{
                  EasyLoading.showInfo(
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
