import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/impression/impression_recu_paiement_pdf.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../helper/constants.dart';
import '../../models/retour_liste_un_new_paiement.dart';

class PaiementScreen extends StatefulWidget {
  static String routeName = "/paiement_listing";

  const PaiementScreen({super.key});

  @override
  State<PaiementScreen> createState() => PaiementScreenState();
}

class PaiementScreenState extends State<PaiementScreen> {
  List<UnNewPaiement> paiements = [];
  RetourUnNewPaiement pai = RetourUnNewPaiement();
  List<Widget> pagesPaiement = [];
  List<UnNewPaiement> paiementAttente = [];
  List<UnNewPaiement> paiementEffectuee = [];
  TextEditingController modePaiementController = TextEditingController();
  int leStatut = 0;
  List<int> ids = [];

  chargerPaiement() async {
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
            .post(Uri.parse('${lienAPI()}liste-paiement'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          pai = RetourUnNewPaiement.fromJson(datas);
          if (pai.code == 200) {
            setState(() {
              paiements = pai.data ?? [];
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
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerPaiement();
      }
    });
  }

  @override
  void dispose() {
    modePaiementController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Paiements effectués et impression de reçu"),
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
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  child: Padding(
                    padding: const EdgeInsets.all(8.0),
                    child: SearchableList<UnNewPaiement>(
                      searchFieldEnabled: true,
                      shrinkWrap: true,
                      autoFocusOnSearch: false,
                      sortWidget: const Icon(Icons.sort),
                      sortPredicate: (a, b) {
                        double mtna = a.id?.toDouble() ?? 0;
                        double mtnb = b.id?.toDouble() ?? 0;
                        return mtna.compareTo(mtnb);
                      },
                      physics: const BouncingScrollPhysics(),
                      builder: (paiements, index, p) => GestureDetector(
                        onTap: () => {
                          Get.toNamed(ImpressionRecuPaiementPdf.routeName, arguments: [2, "",p.id])
                        },
                        child: Padding(
                          padding: const EdgeInsets.all(8.0),
                          child: Container(
                            height: 160,
                            decoration: BoxDecoration(
                              color: ids.contains(p.id) ? Colors.green[100] : Colors.grey[200],
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Row(
                              mainAxisAlignment: mainSpaceBet,
                              children: [
                                const SizedBox(
                                  width: 10,
                                ),
                                Container(
                                    width: 40,
                                    height: 40,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(30),
                                      image: const DecorationImage(
                                          image: AssetImage(
                                              "assets/images/attente.png"),
                                          fit: BoxFit.cover,
                                          opacity: 0.6),
                                    ),
                                    child: Container()),
                                const SizedBox(
                                  width: 10,
                                ),
                                Flexible(
                                  child: Column(
                                    crossAxisAlignment:
                                    CrossAxisAlignment.start,
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Text(
                                        '# ${p.code}',
                                        style: const TextStyle(
                                          color: Colors.red,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        "Total: ${formaterMontant(p.montantTotal?.toDouble() ?? 0)}",
                                        style: const TextStyle(
                                          color: Colors.blue,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        p.libelle.toString(),
                                        style: const TextStyle(
                                          color: Colors.black,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        '${p.datePaiement}',
                                        style: const TextStyle(
                                          color: Colors.black,
                                        ),
                                      ),
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
                      initialList: paiements,
                      filter: (p0) {
                        return paiements
                            .where((c) =>
                        (c.datePaiement.toString().contains(p0) ||
                            c.code.toString().contains(p0) ||
                            c.montantTotal.toString().contains(p0) ||
                            c.service.toString().contains(p0)))
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
      ),
    );
  }

}
