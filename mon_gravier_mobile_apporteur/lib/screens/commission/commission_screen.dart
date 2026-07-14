import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/constants.dart';
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_filleule.dart';
import 'package:mon_gravier_com_apporteur/screens/filleule/paiements/paiement_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../helper/constants.dart';

class CommissionScreen extends StatefulWidget {
  static String routeName = "/filleule";

  const CommissionScreen({super.key});

  @override
  State<CommissionScreen> createState() => _CommissionScreenState();
}

class _CommissionScreenState extends State<CommissionScreen> {

  RetourListeCommission retCommission = RetourListeCommission();
  List<UneCommission> commissions = [];

  chargerCommission() async {
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
            .post(Uri.parse('${lienAPI()}liste-commissions'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('liste-commissions status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(retourHttp.body);
          }
          setState(() {
            retCommission = RetourListeCommission.fromJson(datas);
            commissions = retCommission.data ?? [];

            if (commissions.isNotEmpty) {
              print(commissions.first.toJson());
            }
          });
        } else {
          EasyLoading.showError("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerCommission();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Liste de mes commissions"),
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
            padding: const EdgeInsets.all(15.0),
            child: SearchableList<UneCommission>(
              searchFieldEnabled: true,
              shrinkWrap: true,
              sortWidget: const Icon(Icons.sort),
              sortPredicate: (a, b) {
                int mtna = a.id ?? 0;
                int mtnb = b.id ?? 0;
                return mtna.compareTo(mtnb);
              },
              physics: const BouncingScrollPhysics(),
              builder: (list, index, c) => Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: 150,
                  decoration: BoxDecoration(
                    color: Colors.grey[200],
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Row(
                      children: [
                        const SizedBox(
                          width: 10,
                        ),
                        Container(
                            width: 80,
                            height: 80,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(30),
                              image: const DecorationImage(
                                  image: AssetImage("assets/images/commande.gif"),
                                  fit: BoxFit.cover,
                                  opacity: 0.6),
                            ),
                            child: Container()),
                        const SizedBox(
                          width: 10,
                        ),
                        Flexible(
                          child:  Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text('${c.nom} ${c.prenom} (# ${c.clientId})',
                                style: const TextStyle(
                                  color: Colors.red,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              Text(
                                'Total: ${formaterMontant(c.montantTotal ?? 0)}',
                                style: const TextStyle(
                                  color: Colors.blueAccent,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              Text(
                                'Com.: ${formaterMontant(c.montant ?? 0)}',
                                style: const TextStyle(
                                  color: Colors.green,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              initialList: commissions,
              filter: (p0) {
                return commissions
                    .where((c) => (c.nom.toString().contains(p0) ||
                    c.prenom.toString().contains(p0) ||
                    c.id.toString().contains(p0) ||
                    c.montant.toString().contains(p0) ||
                    c.montantTotal.toString().contains(p0)))
                    .toList();
              },
              inputDecoration: InputDecoration(
                labelText: "Recherchez...",
                fillColor: Colors.white,
                focusedBorder: OutlineInputBorder(
                  borderSide: const BorderSide(
                    color: kPrimaryColor,
                    width: 1.0,
                  ),
                  borderRadius: BorderRadius.circular(10.0),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
