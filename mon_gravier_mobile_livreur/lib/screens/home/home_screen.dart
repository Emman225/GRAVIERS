import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/models/retour_home.dart';
import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';

import '../../helper/constants.dart';
import '../../models/User.dart';

class HomeScreen extends StatefulWidget {
  static String routeName = "/home";

  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {

  bool afficheSolde = true;
  RetourHome retHome = RetourHome(
    data: DataRetourHome(
      demandePaiement: [],
      livreur: Livreur(),
      stats: [Stats(attente: 0, livree: 0)]
    )
  );
  Livreur livreur = Livreur();
  List<DemandePaiement> demandes = [];

  chargerHome({bool sansLoader = false}) async {
    if (await verifierConnexion()) {

      if (sansLoader == false) {
        afficherChargement();
      }

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}home-livreur'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retHome = RetourHome.fromJson(datas);
          if (retHome.code == 200) {
            setState(() {
              demandes = retHome.data?.demandePaiement ?? [];
              livreur = retHome.data?.livreur ?? Livreur();
              user.livreur = livreur;
            });
          } else {
            EasyLoading.showError(retHome.message ?? '');
          }
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (sansLoader == false) {
        fermerChargement();
      }
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    // TODO: implement initState
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if(user.token != null && user.token != ""){
        chargerHome();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Bienvenue sur l'espace livreur IMLOD"),
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          decoration: const BoxDecoration(
            image: DecorationImage(
              image: AssetImage("assets/images/bg.jpg"),
              fit: BoxFit.cover,
              opacity: 0.2,
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: SingleChildScrollView(
              child: Column(
                children: [
                  Container(
                    // width: 250.w,
                    // height: 108.h,
                    margin: EdgeInsets.all(24.00 * 0.4),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 30.00,
                        vertical: 30.00 * 0.6),
                    decoration: BoxDecoration(
                        color: kPrimaryColor,
                        borderRadius: BorderRadius.circular(15)),
                    child: Column(
                      children: [
                        Column(
                          crossAxisAlignment: crossEnd,
                          children: [
                            Column(
                              // Column(
                              crossAxisAlignment: crossStart,
                              children: [
                                Row(
                                  mainAxisAlignment: mainSpaceBet,
                                  children: [
                                    Text(
                                      user.nom.toString(),
                                      style: white16BoldTextStyle,
                                    ),
                                  ],
                                ),
                                addVerticalSpace(5),
                                Row(
                                  mainAxisAlignment: mainSpaceBet,
                                  children: [
                                    Text(
                                      (afficheSolde == true)
                                          ? formaterMontant(user.livreur?.solde?.toDouble() ?? 0)
                                          : "*" * user.livreur!.solde.toString().length,
                                      style: white14BoldTextStyle,
                                    ),
                                    addHorizontalSpace(20),
                                    GestureDetector(
                                        onTap: () {
                                          setState(() {
                                            afficheSolde = !afficheSolde;
                                          });
                                        },
                                        child: Icon(
                                          afficheSolde == true ? Icons.visibility : Icons.visibility_off,
                                          color: whiteColor,
                                          size: 30,
                                        ))
                                  ],
                                ),
                              ],
                            )
                          ],
                        )
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 7),
                    child: Row(
                      mainAxisAlignment: mainSpaceBet,
                      children: [
                        Card(
                          color: Colors.red[200],
                          child: SizedBox(
                            width: (widthOfScreen(context) / 2) - 45,
                            height: 100,
                            child: Center(
                              child: Column(
                                mainAxisAlignment: mainCenter,
                                crossAxisAlignment: crossCenter,
                                children: [
                                  const Text("En Attente", style: black14MediumTextStyle,),
                                  Text(retHome.data!.stats!.first.attente.toString(), style: black18BoldTextStyle,),
                                ],
                              ),
                            ),
                          ),
                        ),
                        addHorizontalSpace(15),
                        Card(
                          color: Colors.green[200],
                          child: SizedBox(
                            width: (widthOfScreen(context) / 2) - 45,
                            height: 100,
                            child: Center(
                              child: Column(
                                mainAxisAlignment: mainCenter,
                                crossAxisAlignment: crossCenter,
                                children: [
                                  const Text("Effectué", style: black14MediumTextStyle,),
                                  Text(retHome.data!.stats!.first.livree.toString(), style: black18BoldTextStyle,),
                                ],
                              ),
                            ),
                          ),
                        )
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    "Liste des paiements effectués",
                    style: red18MediumTextStyle,
                    textAlign: TextAlign.center,
                  ),
                  addVerticalSpace(5),
                  ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: 50.0,
                      maxHeight: MediaQuery.of(context).size.height / 2,
                    ),
                    child: (demandes.isNotEmpty)
                        ? ListView(
                      physics: const BouncingScrollPhysics(),
                      children: <Widget>[_transactionHistoryWidget(context)],
                    )
                        : _aucuneTransaction(),
                  )
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  _aucuneTransaction() {
    return Center(
      child: Column(
        mainAxisAlignment: mainCenter,
        crossAxisAlignment: crossCenter,
        children: [
          const Icon(
            Icons.search_off_sharp,
            color: kPrimaryColor,
            size: 40,
          ),
          addVerticalSpace(10),
          const Text("Aucune Transaction")
        ],
      ),
    );
  }

  _transactionHistoryWidget(BuildContext context) {
    return ListView.builder(
        shrinkWrap: true,
        physics: const BouncingScrollPhysics(),
        scrollDirection: Axis.vertical,
        itemCount: demandes.length,
        itemBuilder: (BuildContext context, int index) {
          DemandePaiement d = demandes[index];
          return Card(
            elevation: 4.0,
            shape: RoundedRectangleBorder(
              side: BorderSide(color: Colors.white70, width: 1),
              borderRadius: BorderRadius.circular(30),
            ),
            child: Padding(
              padding: EdgeInsets.all(24 * 0.3),
              child: Column(
                mainAxisAlignment: mainCenter,
                crossAxisAlignment: crossStart,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text('Compte: ${d.numero_compte.toString()}',),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text('Montant: ${formaterMontant(d.montant?.toDouble() ?? 0)}',),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text("Moyen paiement: ${d.modePaiement}",),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text('Date: ${d.date_demande}',),
                  ),
                ],
              ),
            ),
          );
        });
  }
}
