import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/components/afficher_image_widget.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/impression/impression_commande_pdf.dart';
import 'package:mon_gravier_com/models/Commande.dart';
import 'package:mon_gravier_com/models/InformationsCommande.dart';
import 'package:mon_gravier_com/screens/note_client/note_client_screen.dart';

import '../../constants.dart';
import '../../helper/constants.dart';
import 'components/check_out_card.dart';

class DetailsCommandeScreen extends StatefulWidget {
  static String routeName = "/details_commande";

  const DetailsCommandeScreen({super.key});

  @override
  State<DetailsCommandeScreen> createState() => _DetailsCommandeScreenState();
}

class _DetailsCommandeScreenState extends State<DetailsCommandeScreen> {
  int idCommande = 0;
  String etatCommande = "";
  double montantTotal = 0;
  double remise = 0;
  bool clientATerme = false;
  InformationsCommande infoCom = InformationsCommande();
  UneCommande commande = UneCommande();
  List<LigneCommande> lignes = [];
  TextEditingController motifController = TextEditingController();
  List<int> ids = [];
  List<int> idsProd = [];

  List<Map<int, dynamic>> opts = [
    {1: const Icon(Icons.star)},
    {1: const Icon(Icons.star)},
    {1: const Icon(Icons.star)},
  ];

  chargerDetailsCommande() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}details-commande/$idCommande'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          infoCom = InformationsCommande.fromJson(datas);
          if (infoCom.code == 200) {
            setState(() {
              clientATerme = infoCom.data?.client_a_terme ?? false;
              commande = infoCom.data?.commande ?? UneCommande();
              lignes = infoCom.data?.lignes ?? [];
            });
          } else {
            EasyLoading.showError(infoCom.message ?? '');
          }
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
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
    var datas = Get.arguments;
    idCommande = datas[0];
    etatCommande = datas[1];
    remise = datas[2];
    montantTotal = datas[3];
    motifController = TextEditingController();
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsCommande();
    });
  }

  @override
  void dispose() {
    motifController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          mainAxisAlignment: mainStart,
          crossAxisAlignment: crossStart,
          children: [
            const Text(
              "Détails commande à imprimer ou retourner",
              style: TextStyle(color: Colors.black),
            ),
            Text(
              "${lignes.length} article(s)",
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
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
          if (commande.fichier_bl != null && commande.fichier_bl != '') ...[
            IconButton(
                onPressed: () {
                  Get.toNamed(AfficherImageWidget.routeName, arguments: commande.fichier_bl);
                },
                icon: const Icon(
                  Icons.file_copy_outlined,
                  color: kPrimaryColor,
                  size: 30,
                )),
            addHorizontalSpace(5),
          ]
        ],
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      // floatingActionButton: (user.token != null && user.token != ""&& ids.isNotEmpty)
      //     ? FloatingActionButton.extended(
      //   backgroundColor: greenColor,
      //   foregroundColor: Colors.black,
      //   onPressed: () async {
      //
      //   },
      //   icon: const Icon(Icons.remove_red_eye_outlined, color: whiteColor),
      //   label: const Text('Voir les options possible', style: white16BoldTextStyle,),
      // )
      //     : null,
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          if (lignes.isNotEmpty && commande.id != null) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ImpressionCommandePdf(commande, lignes),
              ),
            );
          } else {
            EasyLoading.showError(
                "Impossible de récupérer les détails de cette opération");
          }
        },
        backgroundColor: greenColor,
        child: const Icon(Icons.print, color: whiteColor),
      ),
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5),
        child: ListView.builder(
          itemCount: lignes.length,
          itemBuilder: (context, index) => GestureDetector(
            onTap: () {
              if (lignes[index].etatLivraison == LIVRAISON_LIVREE) {
                // _demandeActionWidget(lignes[index]);
                setState(() {
                  if (!idsProd.contains(lignes[index].produitId)) {
                    idsProd.add(lignes[index].produitId ?? 0);
                  } else {
                    idsProd.remove(lignes[index].produitId);
                  }

                  if (!ids.contains(lignes[index].id)) {
                    ids.add(lignes[index].id ?? 0);
                    showMenu(
                        context: context,
                        position: const RelativeRect.fromLTRB(100, 100, 100, 100),
                        items: [
                          PopupMenuItem<int>(
                              value: 1,
                              onTap: () async {
                                await Get.toNamed(NoteClientScreen.routeName, arguments: idsProd);
                                setState(() {
                                  ids.clear();
                                  idsProd.clear();
                                });
                              },
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.star,
                                    color: kPrimaryColor,
                                  ),
                                  addHorizontalSpace(10),
                                  const Text('Noter les produits'),
                                ],
                              )),
                          PopupMenuItem<int>(
                              value: 2,
                              onTap: () => _demandeRetourWidget(),
                              child: Row(
                                children: [
                                  const Icon(
                                    Icons.list,
                                    color: kPrimaryColor,
                                  ),
                                  addHorizontalSpace(10),
                                  const Text('Retourner les produits'),
                                ],
                              )),
                        ]);
                  } else {
                    ids.remove(lignes[index].id);
                  }
                });
              } else {
                EasyLoading.showInfo("L'article n'a pas encore été livré!");
              }
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
              child: Container(
                color: ids.contains(lignes[index].id)
                    ? Colors.green[100]
                    : Colors.transparent,
                child: Row(
                  mainAxisAlignment: mainSpaceBet,
                  children: [
                    SizedBox(
                      width: 50,
                      child: AspectRatio(
                        aspectRatio: 0.88,
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF5F6F9),
                            borderRadius: BorderRadius.circular(15),
                          ),
                          child: Image.network(lignes[index].image.toString()),
                        ),
                      ),
                    ),
                    Flexible(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            lignes[index].nom.toString(),
                            style: const TextStyle(
                                color: Colors.black, fontSize: 16),
                            maxLines: 2,
                          ),
                          const SizedBox(height: 8),
                          Text.rich(
                            TextSpan(
                              // Prix STOCKÉ sur la ligne (inclut le prix personnalisé),
                              // repli sur le prix catalogue si absent.
                              text:
                                  "${formaterMontant((lignes[index].prix ?? 0) > 0 ? lignes[index].prix!.toDouble() : (lignes[index].prixMoyen ?? 0).toDouble())} / ${lignes[index].unite.toString()}",
                              style: const TextStyle(
                                  fontWeight: FontWeight.w600,
                                  color: kPrimaryColor),
                              children: [
                                TextSpan(
                                    text: " x${lignes[index].qte}",
                                    style:
                                        Theme.of(context).textTheme.bodyLarge),
                                TextSpan(
                                    text: "\t (${lignes[index].etatLivraison})",
                                    style: red14MediumTextStyle),
                              ],
                            ),
                          )
                        ],
                      ),
                    ),
                    const Icon(Icons.navigate_next_sharp,
                        color: greyColor, size: 20),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: CheckoutCard(
          niveau: 3,
          data: [idCommande, etatCommande, clientATerme],
          montantTotal: montantTotal),
    );
  }

  _demandeRetourWidget() {
    showDialog(
        barrierDismissible: false,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            title: const Text("Motif du retour"),
            elevation: 5.0,
            content: SizedBox(
              height: 200,
              child: Column(
                children: [
                  TextFormField(
                    controller: motifController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.done,
                    maxLines: 6,
                    decoration: const InputDecoration(
                      labelText: "Motif",
                      hintText: "Saisir le motif ici...",
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                  onPressed: () {
                    Get.back();
                  },
                  child: const Text(
                    "Fermer",
                    style: TextStyle(fontSize: 14, color: redColor),
                  )),
              TextButton(
                  onPressed: () async {
                    if (motifController.text.trim().isNotEmpty) {
                      if (await verifierConnexion()) {
                        try {
                          afficherChargement();

                          var param = {
                            'access': user.token.toString(),
                            'type': user.type.toString(),
                            'motif': motifController.text.trim(),
                            'idLigne': ids,
                          };

                          if (kDebugMode) {
                            print(param);
                          }

                          retourHttp = await http
                              .post(
                                  Uri.parse(
                                      '${lienAPI()}demande-retour-produit'),
                                  headers: {"Content-Type": "application/json"},
                                  body: jsonEncode(param))
                              .timeout(const Duration(minutes: 2));

                          var datas = jsonDecode(retourHttp.body);

                          if (kDebugMode) {
                            print(datas);
                          }

                          if (retourHttp.statusCode == 200) {
                            if (datas['code'] == 200) {
                              setState(() {
                                ids.clear();
                                idsProd.clear();
                              });
                              Get.back();
                              EasyLoading.showSuccess(datas['message']);
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
                      Get.back();
                    } else {
                      EasyLoading.showError(
                          "Veuillez saisir le motif du retour de produit");
                    }
                  },
                  child: const Text(
                    "Envoyer",
                    style: TextStyle(fontSize: 14, color: greenColor),
                  ))
            ],
          );
        });
  }
}
