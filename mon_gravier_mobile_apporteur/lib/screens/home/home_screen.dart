import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:mon_gravier_com_apporteur/constants.dart';
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/models/retour_home.dart';

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
      data: DataPaiement(statsList: [Stats(ceMois: 0, cetteAnnee: 0)]));
  Apporteur apporteur = Apporteur();
  List<Paiements> paiements = [];

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
            .post(Uri.parse('${lienAPI()}home-apporteur'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print('home-apporteur status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          retHome = RetourHome.fromJson(datas);
          if (retHome.code == 200) {
            setState(() {
              paiements = retHome.data?.paiementsList ?? [];
              apporteur = retHome.apporteur ?? Apporteur();
              user.apporteur = apporteur;
            });
          } else {
            EasyLoading.showError(retHome.message ?? '');
          }
        } else {
          EasyLoading.showError("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e, stackTrace) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez réessayer plus tard");
        if (kDebugMode) {
          print('Erreur home-apporteur: $e');
          print('StackTrace: $stackTrace');
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
      if (user.token != null && user.token != "") {
        chargerHome();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Bienvenue sur l'espace apporteur IMLOD"),
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
                    margin: const EdgeInsets.all(24.00 * 0.4),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 30.00, vertical: 30.00 * 0.6),
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
                                          ? formaterMontant(user
                                                  .apporteur?.solde
                                                  ?.toDouble() ??
                                              0)
                                          : "*" *
                                              user.apporteur!.solde
                                                  .toString()
                                                  .length,
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
                                          afficheSolde == true
                                              ? Icons.visibility
                                              : Icons.visibility_off,
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
                                  const Text(
                                    "Comm.ce mois",
                                    style: black14MediumTextStyle,
                                  ),
                                  Text(
                                    (retHome.data!.statsList == null || retHome.data!.statsList!.isEmpty || retHome.data!.statsList!.first.ceMois == null)
                                        ? "0"
                                        : formaterMontant(retHome
                                                .data!.statsList!.first.ceMois
                                                ?.toDouble() ??
                                            0),
                                    style: black18BoldTextStyle,
                                  ),
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
                                  const Text(
                                    "Comm. cette année",
                                    style: black14MediumTextStyle,
                                  ),
                                  Text(
                                    (retHome.data!.statsList == null || retHome.data!.statsList!.isEmpty || retHome.data!.statsList!.first.cetteAnnee == null)
                                        ? "0"
                                        : formaterMontant(retHome
                                        .data!.statsList!.first.cetteAnnee
                                        ?.toDouble() ??
                                        0),
                                    style: black18BoldTextStyle,
                                  ),
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
                    child: (paiements.isNotEmpty)
                        ? ListView(
                            physics: const BouncingScrollPhysics(),
                            children: <Widget>[
                              _transactionHistoryWidget(context)
                            ],
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
        itemCount: paiements.length,
        itemBuilder: (BuildContext context, int index) {
          Paiements d = paiements[index];
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
                    child: Text(
                      'Compte: ${d.numeroCompte.toString()}',
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text(
                      'Montant: ${formaterMontant(d.montant?.toDouble() ?? 0)}',
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text(
                      "Moyen paiement: ${d.modePaiement}",
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Text(
                      'Date: ${d.dateDemande}',
                    ),
                  ),
                ],
              ),
            ),
          );
        });
  }
}
