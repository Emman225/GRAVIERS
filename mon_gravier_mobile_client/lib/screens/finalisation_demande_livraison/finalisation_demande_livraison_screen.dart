import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/resume_demande_livraison.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison/components/check_out_card.dart';

import '../../helper/constants.dart';
import 'components/cart_card.dart';

class FinalisationDemandeLivraisonScreen extends StatefulWidget {
  static String routeName = "/detailsDemandeLivraison";

  const FinalisationDemandeLivraisonScreen({super.key});

  @override
  State<FinalisationDemandeLivraisonScreen> createState() =>
      _FinalisationDemandeLivraisonScreenState();
}

class _FinalisationDemandeLivraisonScreenState
    extends State<FinalisationDemandeLivraisonScreen> {

  ResumeDemandeLivraison resume = ResumeDemandeLivraison();
  DetailResume detail = DetailResume();
  double distance = 0;

  @override
  void initState() {
    var data = Get.arguments;
    resume = data[0];
    distance = data[1];
    detail = resume.data ?? DetailResume();
    super.initState();
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Finalisation demande de livraison",
          style: TextStyle(color: Colors.black),
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
      ),
      body: Container(
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
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: 50.0,
                    maxHeight: heightOfScreen(context) / 2,
                  ),
                  child: ListView(
                    physics: const BouncingScrollPhysics(),
                    children: [
                      Card(
                        color: whiteColor,
                        child: Padding(
                          padding: const EdgeInsets.all(15.0),
                          child: Column(
                            crossAxisAlignment: crossStart,
                            children: [
                              addVerticalSpace(10),
                              const Text("Détails de la livraison", style: black18BoldTextStyle,),
                              Divider(color: grayColor,),
                              Text("Départ: "+detail.depart.toString()),
                              Text("Destination: "+detail.destination.toString()),
                              Text("Type livraison: "+detail.typeLivraison.toString()),
                              Text("Mode paiement: "+detail.modePaiement.toString()),
                              Text("Date livraison: "+detail.dateLivraison.toString()),
                              addVerticalSpace(10),
                            ],
                          ),
                        ),
                      ),
                      addVerticalSpace(15),
                      Card(
                        color: whiteColor,
                        child: Padding(
                          padding: const EdgeInsets.all(15.0),
                          child: Column(
                            crossAxisAlignment: crossStart,
                            children: [
                              addVerticalSpace(10),
                              const Text("Produit(s) à livrer", style: black18BoldTextStyle,),
                              Divider(color: grayColor,),
                              ListView.builder(
                                shrinkWrap: true,
                                itemCount: paniers.length,
                                itemBuilder: (context, index) => Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  child: CartCard(cart: paniers[index], showCounter: false),
                                ),
                              ),
                              addVerticalSpace(10),
                            ],
                          ),
                        ),
                      ),
                      addVerticalSpace(15),
                      Card(
                        color: whiteColor,
                        child: Padding(
                          padding: const EdgeInsets.all(15.0),
                          child: Column(
                            crossAxisAlignment: crossStart,
                            children: [
                              addVerticalSpace(10),
                              const Text("Coût de livraison", style: black18BoldTextStyle,),
                              Divider(color: grayColor,),
                              Text("Distance: $distance km x${paniers.length}"),
                              Text("Cout de livraison: ${formaterMontant(detail.montant?.toDouble() ?? 0)}"),
                              addVerticalSpace(10),
                            ],
                          ),
                        ),
                      ),
                    ],
                  )

                )

              ),
            ),
      bottomNavigationBar: CheckoutCard(niveau: 2),
    );
  }
}
