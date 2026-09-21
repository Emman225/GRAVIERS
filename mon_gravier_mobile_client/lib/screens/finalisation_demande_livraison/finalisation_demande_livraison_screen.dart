import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/resume_demande_livraison.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison/components/check_out_card.dart';

import '../../components/bouton_retour.dart';
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
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: Container(
              width: double.infinity,
              height: heightOfScreen(context),
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

                              // LA TAXE, QUAND IL Y EN A UNE.
                              //
                              // La TVA sur le transport est une option du
                              // back-office. Activee, le total depasse le tarif
                              // de la grille : le client doit voir d'ou vient
                              // l'ecart, sinon le prix parait faux. Desactivee,
                              // le cout du transport EST le total, et afficher
                              // « TVA : 0 » sur chaque course entretiendrait le
                              // doute sur ce qui est facture.
                              if ((detail.tva ?? 0) > 0) ...[
                                Text("Coût du transport : ${formaterMontant(detail.montantHt?.toDouble() ?? 0)}"),
                                Text("TVA : ${formaterMontant(detail.tva?.toDouble() ?? 0)}"),
                                Text(
                                  "Total à payer : ${formaterMontant(detail.montant?.toDouble() ?? 0)}",
                                  style: black18BoldTextStyle,
                                ),
                              ] else
                                Text("Coût de la livraison : ${formaterMontant(detail.montant?.toDouble() ?? 0)}"),
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
