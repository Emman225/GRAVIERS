import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/models/code_promo.dart';
import 'package:mon_gravier_com/screens/devis/devis_screen.dart';
import 'package:roundcheckbox/roundcheckbox.dart';

import '../../helper/constants.dart';
import '../init_screen.dart';
import 'components/cart_card.dart';
import 'components/check_out_card.dart';

class CartScreen extends StatefulWidget {
  static String routeName = "/cart";

  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  TextEditingController libelleController = TextEditingController();
  TextEditingController codePromoController = TextEditingController();
  Timer? timer;

  @override
  void initState() {
    libelleController = TextEditingController();
    codePromoController = TextEditingController();
    reduction = Reduction();
    montantTva = 0;
    coutReduction = 0;
    utiliserPoint = false;
    meFaireLivre = true;
    if(user.token == null){
      meFaireLivre = false;
    }
    if (kDebugMode) {
      print(tva);
    }
    super.initState();
    timer = Timer.periodic(const Duration(seconds: 3), (Timer t) {
      setState(() {});
    });
  }

  @override
  void dispose() {
    libelleController.dispose();
    codePromoController.dispose();
    timer?.cancel();
    super.dispose();
  }

  allerAccueil() async {
    if (kDebugMode) {
      print("---------> Fermeture");
    }
    Get.offAllNamed(InitScreen.routeName);
    return true;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          children: [
            const Text(
              "Votre Panier",
              style: TextStyle(color: Colors.black),
            ),
            Text(
              "${paniers.length} article(s)",
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: () async => {
              if (await confirmationAction(
                  context, "Attention", "Voulez-vous vider votre pannier ?"))
                {
                  setState(() {
                    paniers.clear();
                  })
                }
            },
            icon: const Icon(
              Icons.clear,
              color: kPrimaryColor,
            ),
          )
        ],
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () => allerAccueil(),
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
      body: paniers.isEmpty
          ? Center(
              child: Image.asset('assets/images/empty_card.gif'),
            )
          : Container(
              width: double.infinity,
              height: heightOfScreen(context),
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage("assets/images/bg.jpg"),
                  fit: BoxFit.cover,
                  opacity: 0.1,
                ),
              ),
              child: ListView(
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    child: ListView.builder(
                      shrinkWrap: true,
                      itemCount: paniers.length,
                      itemBuilder: (context, index) => Padding(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        child: Dismissible(
                          key: Key(paniers[index].product.id.toString()),
                          direction: DismissDirection.endToStart,
                          onDismissed: (direction) {
                            setState(() {
                              paniers.removeAt(index);
                            });
                          },
                          background: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFE6E6),
                              borderRadius: BorderRadius.circular(15),
                            ),
                            child: Row(
                              children: [
                                const Spacer(),
                                SvgPicture.asset("assets/icons/Trash.svg"),
                              ],
                            ),
                          ),
                          child: CartCard(
                            cart: paniers[index],
                            showCounter:
                                paniers[index].product.type_affaire == VENTE
                                    ? true
                                    : false,
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (reduction.id == null || reduction.id! <= 0) ...[
                    const Divider(),
                    Row(
                      children: [
                        Flexible(
                          flex: 4,
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 20),
                            child: TextFormField(
                              keyboardType: TextInputType.text,
                              controller: codePromoController,
                              textInputAction: TextInputAction.done,
                              decoration: const InputDecoration(
                                labelText: "Code promo",
                                hintText: "Saisissez ici...",
                              ),
                            ),
                          ),
                        ),
                        Flexible(
                          flex: 2,
                          child: Padding(
                            padding: const EdgeInsets.only(right: 10),
                            child: ElevatedButton(
                              style: ButtonStyle(
                                  backgroundColor: MaterialStateProperty.all(
                                      Colors.lightGreen)),
                              onPressed: () => _verifierCodePromo(),
                              child: const Padding(
                                padding: EdgeInsets.symmetric(vertical: 25),
                                child: Text("Vérifier"),
                              ),
                            ),
                          ),
                        )
                      ],
                    ),
                  ],
                  if (paniers.isNotEmpty) ...[
                    const Divider(),
                    Row(
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(left: 8, right: 8),
                          child: RoundCheckBox(
                            isChecked: meFaireLivre,
                            onTap: (selected) {
                                if(user.token == null){
                                  meFaireLivre = false;
                                  EasyLoading.showError("Veuillez vous inscrire ou vous connecter pour être livrer");
                                }else{
                                  meFaireLivre = selected!;
                                }
                              setState(() {});
                            },
                          ),
                        ),
                        const Text(
                          "Me faire livrer",
                          style: black16BoldTextStyle,
                        )
                      ],
                    ),
                  ],
                  if (nombrePoint > 0) ...[
                    const Divider(),
                    Row(
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(left: 8, right: 8),
                          child: RoundCheckBox(
                            isChecked: utiliserPoint,
                            onTap: (selected) {
                              setState(() {
                                utiliserPoint = selected!;
                              });
                              double mtn = montantPoint * nombrePoint;
                              if (utiliserPoint == true) {
                                EasyLoading.showSuccess(
                                    "Réduction de ${formaterMontant(mtn)} appliquée");
                              } else {
                                EasyLoading.showError(
                                    "Réduction de ${formaterMontant(mtn)} non appliquée");
                              }
                            },
                          ),
                        ),
                        Text(
                          utiliserPoint == false
                              ? "Utiliser mes $nombrePoint $devise"
                              : "Ne pas utiliser mes $nombrePoint $devise",
                          style: black16BoldTextStyle,
                        )
                      ],
                    ),
                  ],
                  if (tva > 0) ...[
                    addVerticalSpace(20),
                    Card(
                        child: Padding(
                      padding: const EdgeInsets.all(20.0),
                      child: Text(
                        "Montant TVA: ${formaterMontant(montantTva)}",
                        textAlign: TextAlign.center,
                        style: red18MediumTextStyle,
                      ),
                    )),
                  ],
                  const Divider(),
                ],
              ),
            ),
      bottomNavigationBar: CheckoutCard(niveau: 1, data: const []),
    );
  }

  _verifierCodePromo() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "code": codePromoController.text.trim(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}verifier-code-promo'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          RetourCodePromo retourPromo = RetourCodePromo.fromJson(datas);
          if (retourPromo.code == 200) {
            setState(() {
              reduction = retourPromo.data ?? Reduction();
            });
            EasyLoading.showSuccess(retourPromo.message ?? '');
          } else {
            EasyLoading.showError(retourPromo.message ?? '');
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
}
