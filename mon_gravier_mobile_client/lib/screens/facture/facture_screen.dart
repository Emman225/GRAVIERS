import 'dart:convert';

import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/liste_paiement.dart';

import '../../helper/constants.dart';
import 'components/facture_liste_screen.dart';

class FactureScreen extends StatefulWidget {
  static String routeName = "/facture_listing";

  const FactureScreen({super.key});

  @override
  State<FactureScreen> createState() => FactureScreenState();
}

class FactureScreenState extends State<FactureScreen> {
  List<UnPaiement> paiements = [];
  ListePaiement pai = ListePaiement();
  List<Widget> pagesPaiement = [];
  List<UnPaiement> paiementAttente = [];
  List<UnPaiement> paiementEffectuee = [];

  double _totalAttente = 0;

  chargerFacture() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "statut": [1,2],
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-facture'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          pai = ListePaiement.fromJson(datas);
          if (pai.code == 200) {
            setState(() {
              paiements = pai.data ?? [];
              paiementAttente = paiements
                  .where((c) => (c.statut == 2))
                  .toList();
              paiementEffectuee = paiements
                  .where((c) => (c.statut == 1))
                  .toList();

              for (var p in paiementAttente) { _totalAttente += p.montant?.toDouble() ?? 0; }

              pagesPaiement = [
                FactureListeScreen(paiements: paiementAttente, total: _totalAttente),
                FactureListeScreen(paiements: paiementEffectuee, total: _totalAttente,),
              ];
            });
          } else {
            EasyLoading.showError(pai.message ?? '');
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
    pagesPaiement = [
      FactureListeScreen(paiements: paiementAttente, total: _totalAttente),
      FactureListeScreen(paiements: paiementEffectuee, total: _totalAttente),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerFacture();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste des factures"),
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
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
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
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ContainedTabBarView(
                      tabBarProperties: TabBarProperties(
                        background: Container(
                          margin: const EdgeInsets.only(bottom: 5),
                          decoration: const BoxDecoration(
                            color: kSecondaryColor,
                            borderRadius: BorderRadius.all(Radius.circular(8.0)),
                          ),
                        ),
                        indicatorColor: kPrimaryColor,
                        labelColor: Colors.white,
                        unselectedLabelColor: Colors.black,
                      ),
                      tabs: const [
                        Text('En Attente', style: white16BoldTextStyle),
                        Text('Payée', style: white16BoldTextStyle),
                      ],
                      views: pagesPaiement,
                      onChange: (index) {
                        if (kDebugMode) {
                          print(index);
                        }
                      }
                  ),
                ),
              ),
      ),
    );
  }

}
